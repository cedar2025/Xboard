<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\DistributionApp;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $currentVersion = trim((string) ($params['version'] ?? ''));
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
            });

        if ($app) {
            $query->where('app_id', $app->id);
        }

        $candidates = $query->get();
        $latest = Collection::make($candidates)->sort(function (AppVersion $left, AppVersion $right) {
            return $this->compareVersions($right->version, $left->version)
                ?: ($right->build_number <=> $left->build_number)
                ?: ((int) $right->published_at <=> (int) $left->published_at)
                ?: ($right->id <=> $left->id);
        })->first();

        if (!$latest) {
            return $this->success([
                'has_update' => false,
                'force' => false,
                'latest' => null,
            ]);
        }

        $versionComparison = $this->compareVersions($latest->version, $currentVersion);
        $hasUpdate = $versionComparison !== null
            ? $versionComparison > 0
            : $latest->build_number > $currentBuild;
        $force = $hasUpdate && ($latest->is_force || $currentBuild < $latest->min_supported_build);
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

    private function compareVersions(string $left, string $right): ?int
    {
        $leftParts = $this->normalizeVersionParts($left);
        $rightParts = $this->normalizeVersionParts($right);

        if ($leftParts === null || $rightParts === null) {
            return null;
        }

        $length = max(count($leftParts), count($rightParts));
        for ($index = 0; $index < $length; $index++) {
            $leftPart = $leftParts[$index] ?? 0;
            $rightPart = $rightParts[$index] ?? 0;
            if ($leftPart === $rightPart) {
                continue;
            }

            return $leftPart <=> $rightPart;
        }

        return 0;
    }

    private function normalizeVersionParts(string $version): ?array
    {
        $normalized = strtolower(trim($version));
        $normalized = preg_replace('/^v/', '', $normalized);

        if (!is_string($normalized) || !preg_match('/^\d+(?:\.\d+)*(?:[+-][a-z0-9._-]+)?$/', $normalized)) {
            return null;
        }

        $numericVersion = preg_split('/[+-]/', $normalized, 2)[0];
        $parts = array_map('intval', explode('.', $numericVersion));

        while (count($parts) > 1 && end($parts) === 0) {
            array_pop($parts);
        }

        return $parts;
    }
}
