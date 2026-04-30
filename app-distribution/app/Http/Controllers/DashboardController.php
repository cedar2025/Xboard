<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use App\Models\DeviceInstallation;
use App\Models\DistributionApp;
use App\Models\DownloadLog;
use App\Models\UpdateEvent;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'apps' => DistributionApp::count(),
            'published_versions' => AppVersion::where('status', AppVersion::STATUS_PUBLISHED)->count(),
            'downloads' => DownloadLog::count(),
            'active_7d' => DeviceInstallation::where('last_seen_at', '>=', now()->subDays(7))->count(),
        ];

        $latestVersions = AppVersion::with(['app', 'artifact'])
            ->orderByDesc('published_at')
            ->orderByDesc('build_number')
            ->limit(8)
            ->get();

        $platforms = DeviceInstallation::query()
            ->select('platform', DB::raw('count(*) as total'))
            ->groupBy('platform')
            ->orderByDesc('total')
            ->get();

        $events = UpdateEvent::query()
            ->select('event', DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('event')
            ->orderByDesc('total')
            ->get();

        return view('dashboard', compact('stats', 'latestVersions', 'platforms', 'events'));
    }
}
