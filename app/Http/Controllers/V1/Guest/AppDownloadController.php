<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\AppArtifact;
use App\Models\AppDownloadLog;
use App\Models\AppVersion;
use App\Services\AppArtifactStorage;
use App\Services\AppDownloadVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppDownloadController extends Controller
{
    public function index(AppDownloadVerificationService $verification)
    {
        $versions = AppVersion::query()
            ->with(['app', 'artifact'])
            ->whereNotNull('app_id')
            ->where('is_enabled', true)
            ->whereNotNull('published_at')
            ->whereHas('app', fn ($query) => $query->where('is_active', true))
            ->whereHas('artifact')
            ->orderBy('platform')
            ->orderBy('app_id')
            ->orderByDesc('build_number')
            ->orderByDesc('published_at')
            ->get();

        $platforms = $versions
            ->groupBy('platform')
            ->map(function ($platformVersions, $platform) {
                return [
                    'platform' => $platform,
                    'apps' => $platformVersions
                        ->groupBy('app_id')
                        ->map(function ($appVersions) {
                            $app = $appVersions->first()->app;
                            return [
                                'id' => $app->id,
                                'name' => $app->name,
                                'app_key' => $app->app_key,
                                'description' => $app->description,
                                'packages' => $appVersions->map(fn (AppVersion $version) => [
                                    'version_id' => $version->id,
                                    'artifact_id' => $version->artifact->id,
                                    'platform' => $version->platform,
                                    'channel' => $version->channel,
                                    'arch' => $version->arch,
                                    'version' => $version->version,
                                    'build_number' => $version->build_number,
                                    'min_supported_build' => $version->min_supported_build,
                                    'file_size' => $version->artifact->file_size,
                                    'sha256' => $version->artifact->sha256,
                                    'release_notes' => $version->release_notes,
                                    'is_force' => $version->is_force,
                                    'published_at' => $version->published_at,
                                ])->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        return $this->success([
            'platforms' => $platforms,
            'turnstile' => $verification->publicSettings(),
        ]);
    }

    public function download(Request $request, AppArtifact $artifact, AppArtifactStorage $storage)
    {
        $artifact->load('version.app');
        abort_unless($artifact->version?->isPublished(), 404);
        abort_unless((bool) $artifact->version->app?->is_active, 404);

        $downloadPath = $storage->absolutePath($artifact);
        if (!$storage->exists($artifact) || !is_file($downloadPath)) {
            Log::warning('App download artifact file missing', [
                'artifact_id' => $artifact->id,
                'app_version_id' => $artifact->app_version_id,
                'disk' => $artifact->disk,
                'path' => $artifact->path,
            ]);

            return $this->fail([404, '安装包文件不存在，请联系管理员修复文件绑定']);
        }

        AppDownloadLog::create([
            'app_id' => $artifact->version->app_id,
            'app_version_id' => $artifact->version->id,
            'app_artifact_id' => $artifact->id,
            'user_id' => $request->query('user_id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'downloaded_at' => now(),
        ]);

        return response()->download(
            $downloadPath,
            $artifact->original_name,
            ['Content-Type' => $storage->downloadMimeType($artifact)]
        );
    }
}
