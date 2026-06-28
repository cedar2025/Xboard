<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\DistributionApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AppUpdateController extends Controller
{
    public function check(Request $request)
    {
        $params = $request->validate([
            'platform' => 'required|string|max:32',
            'channel' => 'nullable|string|max:32',
            'arch' => 'nullable|string|max:32',
            'version' => 'nullable|string|max:32',
            'build' => 'nullable|integer|min:0',
            'app_key' => 'nullable|string|max:64',
        ]);

        $platform = strtolower($params['platform']);
        $channel = strtolower($params['channel'] ?? 'stable');
        $arch = isset($params['arch']) ? strtolower($params['arch']) : null;
        $currentBuild = (int) ($params['build'] ?? 0);
        $appKey = trim((string) ($params['app_key'] ?? ''));

        $app = null;
        if ($appKey !== '') {
            $app = DistributionApp::where('app_key', $appKey)
                ->where('is_active', true)
                ->first();

            if (!$app) {
                return $this->success([
                    'has_update' => false,
                    'force' => false,
                    'latest' => null,
                ]);
            }
        }

        $query = AppVersion::with(['app', 'artifact'])
            ->where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_enabled', true)
            ->whereNotNull('published_at')
            ->whereHas('artifact')
            ->whereHas('app', function ($query) {
                $query->where('is_active', true);
            })
            ->where(function ($query) use ($arch) {
                if (!$arch) {
                    $query->whereNull('arch');
                    return;
                }
                $query->where('arch', $arch)->orWhereNull('arch');
            })
            ->orderByDesc('build_number')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($app) {
            $query->where('app_id', $app->id);
        }

        $latest = $query->first();

        if (!$latest) {
            return $this->success([
                'has_update' => false,
                'force' => false,
                'latest' => null,
            ]);
        }

        $hasUpdate = $latest->build_number > $currentBuild;
        $force = $latest->is_force || $currentBuild < $latest->min_supported_build;
        $latestPayload = $latest->toClientArray();
        $latestPayload['download_url'] = URL::temporarySignedRoute(
            'app-downloads.download',
            now()->addMinutes(30),
            [
                'artifact' => $latest->artifact->id,
                'source' => 'app_update',
            ],
            false
        );

        return $this->success([
            'has_update' => $hasUpdate,
            'force' => $force,
            'latest' => $latestPayload,
        ]);
    }
}
