<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceHeartbeat;
use App\Models\DeviceInstallation;
use App\Models\DistributionApp;
use App\Models\UpdateEvent;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function heartbeat(Request $request)
    {
        $data = $request->validate([
            'app_key' => ['required', 'string', 'max:64'],
            'installation_id' => ['required', 'string', 'max:128'],
            'platform' => ['required', 'string', 'in:macos,windows'],
            'channel' => ['nullable', 'string', 'in:stable,beta'],
            'arch' => ['nullable', 'string', 'max:32'],
            'version' => ['nullable', 'string', 'max:32'],
            'build' => ['nullable', 'integer', 'min:0'],
            'os_version' => ['nullable', 'string', 'max:128'],
        ]);

        $app = DistributionApp::where('app_key', $data['app_key'])
            ->where('is_active', true)
            ->firstOrFail();

        $installation = DeviceInstallation::firstOrNew([
            'app_id' => $app->id,
            'installation_id' => $data['installation_id'],
        ]);

        if (!$installation->exists) {
            $installation->first_seen_at = now();
        }

        $installation->fill([
            'platform' => $data['platform'],
            'channel' => $data['channel'] ?? 'stable',
            'arch' => $data['arch'] ?? null,
            'version' => $data['version'] ?? null,
            'build_number' => (int) ($data['build'] ?? 0),
            'os_version' => $data['os_version'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_seen_at' => now(),
        ])->save();

        DeviceHeartbeat::create([
            'device_installation_id' => $installation->id,
            'version' => $data['version'] ?? null,
            'build_number' => (int) ($data['build'] ?? 0),
            'os_version' => $data['os_version'] ?? null,
            'reported_at' => now(),
        ]);

        return $this->apiSuccess(['accepted' => true]);
    }

    public function updateResult(Request $request)
    {
        $data = $request->validate([
            'app_key' => ['required', 'string', 'max:64'],
            'installation_id' => ['nullable', 'string', 'max:128'],
            'event' => ['required', 'string', 'in:download_clicked,download_opened,installed_after_update'],
            'platform' => ['nullable', 'string', 'in:macos,windows'],
            'channel' => ['nullable', 'string', 'in:stable,beta'],
            'from_version' => ['nullable', 'string', 'max:32'],
            'from_build' => ['nullable', 'integer', 'min:0'],
            'payload' => ['nullable', 'array'],
        ]);

        $app = DistributionApp::where('app_key', $data['app_key'])
            ->where('is_active', true)
            ->firstOrFail();

        UpdateEvent::create([
            'app_id' => $app->id,
            'installation_id' => $data['installation_id'] ?? null,
            'event' => $data['event'],
            'platform' => $data['platform'] ?? null,
            'channel' => $data['channel'] ?? 'stable',
            'from_version' => $data['from_version'] ?? null,
            'from_build' => (int) ($data['from_build'] ?? 0),
            'payload' => $data['payload'] ?? null,
            'created_at' => now(),
        ]);

        return $this->apiSuccess(['accepted' => true]);
    }
}
