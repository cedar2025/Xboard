<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\DeviceInstallation;
use App\Models\DistributionApp;
use App\Models\UpdateEvent;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function check(Request $request)
    {
        $data = $request->validate([
            'app_key' => ['required', 'string', 'max:64'],
            'platform' => ['required', 'string', 'in:macos,windows'],
            'channel' => ['nullable', 'string', 'in:stable,beta'],
            'version' => ['nullable', 'string', 'max:32'],
            'build' => ['nullable', 'integer', 'min:0'],
            'arch' => ['nullable', 'string', 'max:32'],
            'installation_id' => ['nullable', 'string', 'max:128'],
            'os_version' => ['nullable', 'string', 'max:128'],
        ]);

        $app = DistributionApp::where('app_key', $data['app_key'])
            ->where('is_active', true)
            ->firstOrFail();

        $channel = $data['channel'] ?? 'stable';
        $currentBuild = (int) ($data['build'] ?? 0);
        $arch = $data['arch'] ?? null;

        if (!empty($data['installation_id'])) {
            $this->upsertInstallation($request, $app, $data, $channel, $currentBuild);
        }

        $latest = AppVersion::with('artifact')
            ->where('app_id', $app->id)
            ->where('platform', $data['platform'])
            ->where('channel', $channel)
            ->where('status', AppVersion::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where(function ($query) use ($arch) {
                if (!$arch) {
                    $query->whereNull('arch');
                    return;
                }
                $query->where('arch', $arch)->orWhereNull('arch');
            })
            ->orderByDesc('build_number')
            ->orderByDesc('published_at')
            ->first();

        if (!$latest || !$latest->artifact) {
            $this->recordEvent($app, null, $data, 'check_no_release', $currentBuild);
            return $this->apiSuccess([
                'has_update' => false,
                'force' => false,
                'latest' => null,
            ]);
        }

        $hasUpdate = $latest->build_number > $currentBuild;
        $force = $latest->is_force || $currentBuild < $latest->min_supported_build;
        $event = $force ? 'check_force' : ($hasUpdate ? 'check_update' : 'check_current');
        $this->recordEvent($app, $latest, $data, $event, $currentBuild);

        return $this->apiSuccess([
            'has_update' => $hasUpdate,
            'force' => $force,
            'latest' => [
                'version' => $latest->version,
                'build_number' => $latest->build_number,
                'min_supported_build' => $latest->min_supported_build,
                'platform' => $latest->platform,
                'channel' => $latest->channel,
                'arch' => $latest->arch,
                'download_url' => route('download.artifact', [
                    'artifact' => $latest->artifact->id,
                    'installation_id' => $data['installation_id'] ?? null,
                ]),
                'file_size' => $latest->artifact->file_size,
                'sha256' => $latest->artifact->sha256,
                'release_notes' => $latest->release_notes,
                'published_at' => optional($latest->published_at)->timestamp,
            ],
        ]);
    }

    private function upsertInstallation(Request $request, DistributionApp $app, array $data, string $channel, int $build): void
    {
        $installation = DeviceInstallation::firstOrNew([
            'app_id' => $app->id,
            'installation_id' => $data['installation_id'],
        ]);

        if (!$installation->exists) {
            $installation->first_seen_at = now();
        }

        $installation->fill([
            'platform' => $data['platform'],
            'channel' => $channel,
            'arch' => $data['arch'] ?? null,
            'version' => $data['version'] ?? null,
            'build_number' => $build,
            'os_version' => $data['os_version'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_seen_at' => now(),
        ])->save();
    }

    private function recordEvent(DistributionApp $app, ?AppVersion $version, array $data, string $event, int $build): void
    {
        UpdateEvent::create([
            'app_id' => $app->id,
            'app_version_id' => $version?->id,
            'installation_id' => $data['installation_id'] ?? null,
            'event' => $event,
            'platform' => $data['platform'] ?? null,
            'channel' => $data['channel'] ?? 'stable',
            'from_version' => $data['version'] ?? null,
            'from_build' => $build,
            'payload' => ['arch' => $data['arch'] ?? null],
            'created_at' => now(),
        ]);
    }
}
