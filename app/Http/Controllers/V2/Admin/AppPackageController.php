<?php

namespace App\Http\Controllers\V2\Admin;

use App\Helpers\ResponseEnum;
use App\Http\Controllers\Controller;
use App\Models\AppDownloadLog;
use App\Models\AppVersion;
use App\Models\DistributionApp;
use App\Services\AppDownloadVerificationService;
use App\Services\AppArtifactStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AppPackageController extends Controller
{
    public function settings(AppDownloadVerificationService $verification)
    {
        $settings = $verification->settings();

        return $this->success([
            'app_download_turnstile_enable' => $settings['enabled'],
            'app_download_turnstile_site_key' => admin_setting('app_download_turnstile_site_key', ''),
            'app_download_turnstile_secret_key' => admin_setting('app_download_turnstile_secret_key', ''),
            'effective_turnstile_site_key' => $settings['site_key'],
            'uses_global_turnstile_fallback' => $settings['uses_global_fallback'],
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'app_download_turnstile_enable' => 'nullable|boolean',
            'app_download_turnstile_site_key' => 'nullable|string|max:255',
            'app_download_turnstile_secret_key' => 'nullable|string|max:255',
        ]);

        admin_setting([
            'app_download_turnstile_enable' => (int) ($data['app_download_turnstile_enable'] ?? 0),
            'app_download_turnstile_site_key' => $data['app_download_turnstile_site_key'] ?? '',
            'app_download_turnstile_secret_key' => $data['app_download_turnstile_secret_key'] ?? '',
        ]);

        return $this->success(true);
    }

    public function apps(Request $request)
    {
        $apps = DistributionApp::query()
            ->withCount('versions')
            ->when($request->input('keyword'), function ($query, $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('app_key', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('name')
            ->get();

        return $this->success($apps);
    }

    public function saveApp(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:v2_distribution_apps,id',
            'name' => 'required|string|max:128',
            'app_key' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
            ],
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $data['app_key'] = $data['app_key'] ?: Str::slug($data['name']);
        if (!$data['app_key']) {
            $data['app_key'] = 'app-' . Str::lower(Str::random(8));
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $existingByKey = DistributionApp::query()
            ->where('app_key', $data['app_key'])
            ->first();
        if ($existingByKey && (empty($data['id']) || (int) $existingByKey->id !== (int) $data['id'])) {
            if (!empty($data['id'])) {
                return $this->fail([400, '应用标识已存在，请更换应用名称或标识']);
            }
            $data['id'] = $existingByKey->id;
        }

        if (empty($data['id'])) {
            $existingByName = DistributionApp::query()
                ->where('name', $data['name'])
                ->first();
            if ($existingByName) {
                $data['id'] = $existingByName->id;
                $data['app_key'] = $existingByName->app_key;
            }
        }

        $app = empty($data['id'])
            ? DistributionApp::create($data)
            : tap(DistributionApp::findOrFail($data['id']))->update($data);

        return $this->success($app);
    }

    public function dropApp(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:v2_distribution_apps,id']);
        $app = DistributionApp::withCount('versions')->findOrFail($request->input('id'));
        if ($app->versions_count > 0) {
            return $this->fail([400, '请先删除该应用下的版本包']);
        }

        $app->delete();

        return $this->success(true);
    }

    public function versions(Request $request)
    {
        $versions = AppVersion::query()
            ->with(['app', 'artifact'])
            ->whereNotNull('app_id')
            ->when($request->input('app_id'), fn ($query, $appId) => $query->where('app_id', $appId))
            ->when($request->input('platform'), fn ($query, $platform) => $query->where('platform', strtolower($platform)))
            ->when($request->input('channel'), fn ($query, $channel) => $query->where('channel', strtolower($channel)))
            ->orderByDesc('published_at')
            ->orderByDesc('build_number')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20));

        return $this->paginate($versions);
    }

    public function saveVersion(Request $request, AppArtifactStorage $storage)
    {
        $request->merge([
            'arch' => $request->input('arch') ?: null,
            'sha256' => $request->input('sha256') ?: null,
            'channel' => $request->input('channel') ?: 'stable',
        ]);

        $data = $request->validate([
            'id' => 'nullable|integer|exists:v2_app_versions,id',
            'app_id' => 'required|integer|exists:v2_distribution_apps,id',
            'platform' => ['required', 'string', 'max:32', Rule::in(['android', 'windows', 'macos', 'ios', 'linux'])],
            'channel' => ['nullable', 'string', 'max:32', Rule::in(['stable', 'beta'])],
            'arch' => 'nullable|string|max:32',
            'version' => 'required|string|max:32',
            'build_number' => 'required|integer|min:1',
            'min_supported_build' => 'nullable|integer|min:0',
            'release_notes' => 'nullable|string|max:20000',
            'is_force' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
            'published_at' => 'nullable|integer|min:0',
            'artifact' => 'nullable|file|max:2097152',
        ]);
        unset($data['artifact']);

        $data['platform'] = strtolower($data['platform']);
        $data['channel'] = strtolower($data['channel'] ?? 'stable');
        $data['arch'] = $data['arch'] ? strtolower($data['arch']) : null;
        $data['min_supported_build'] = (int) ($data['min_supported_build'] ?? 0);
        $data['is_force'] = (bool) ($data['is_force'] ?? false);
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['published_at'] = $data['published_at'] ?? ($data['is_enabled'] ? time() : null);
        $data['download_url'] = '';

        if (empty($data['id']) && !$request->hasFile('artifact')) {
            return $this->fail([400, '创建版本必须上传安装包']);
        }

        try {
            $version = empty($data['id'])
                ? AppVersion::create($data)
                : tap(AppVersion::findOrFail($data['id']))->update($data);

            if ($request->hasFile('artifact')) {
                $storage->store($version->load('artifact'), $request->file('artifact'), $request->user()?->id);
            }
        } catch (InvalidArgumentException $e) {
            return $this->fail([400, $e->getMessage()]);
        } catch (\Throwable $e) {
            \Log::error($e);
            return $this->fail([500, '保存失败，请检查应用、平台、渠道、架构和构建号是否重复']);
        }

        return $this->success($version->load(['app', 'artifact']));
    }

    public function publish(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:v2_app_versions,id']);
        $version = AppVersion::with('artifact')->findOrFail($request->input('id'));
        if (!$version->artifact) {
            return $this->fail([400, '发布前必须上传安装包']);
        }

        $version->update([
            'is_enabled' => true,
            'published_at' => $version->published_at ?: time(),
        ]);

        return $this->success(true);
    }

    public function disable(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:v2_app_versions,id']);
        AppVersion::findOrFail($request->input('id'))->update(['is_enabled' => false]);

        return $this->success(true);
    }

    public function drop(Request $request, AppArtifactStorage $storage)
    {
        $request->validate(['id' => 'required|integer|exists:v2_app_versions,id']);
        $version = AppVersion::with('artifact')->findOrFail($request->input('id'));
        if ($version->isPublished()) {
            return $this->fail([400, '已发布版本不能直接删除，请先下架']);
        }

        if ($version->artifact) {
            $storage->deleteFile($version->artifact);
        }
        $version->delete();

        return $this->success(true);
    }

    public function logs(Request $request)
    {
        $logs = AppDownloadLog::query()
            ->with(['app', 'version', 'artifact'])
            ->when($request->input('app_id'), fn ($query, $appId) => $query->where('app_id', $appId))
            ->when($request->input('artifact_id'), fn ($query, $artifactId) => $query->where('app_artifact_id', $artifactId))
            ->orderByDesc('downloaded_at')
            ->paginate((int) $request->input('per_page', 20));

        return $this->paginate($logs);
    }
}
