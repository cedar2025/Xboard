<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

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
        ]);

        $platform = strtolower($params['platform']);
        $channel = strtolower($params['channel'] ?? 'stable');
        $arch = isset($params['arch']) ? strtolower($params['arch']) : null;
        $currentBuild = (int) ($params['build'] ?? 0);

        $query = AppVersion::where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_enabled', true)
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
            ->orderByDesc('id');

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

        return $this->success([
            'has_update' => $hasUpdate,
            'force' => $force,
            'latest' => $latest->toClientArray(),
        ]);
    }
}
