<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function fetch(Request $request)
    {
        $query = AppVersion::query()
            ->when($request->input('platform'), fn ($query, $platform) => $query->where('platform', strtolower($platform)))
            ->when($request->input('channel'), fn ($query, $channel) => $query->where('channel', strtolower($channel)))
            ->orderByDesc('published_at')
            ->orderByDesc('build_number')
            ->orderByDesc('id');

        return $this->success($query->get());
    }

    public function save(Request $request)
    {
        $request->merge([
            'arch' => $request->input('arch') ?: null,
            'sha256' => $request->input('sha256') ?: null,
            'channel' => $request->input('channel') ?: 'stable',
        ]);

        $data = $request->validate([
            'id' => 'nullable|integer|exists:v2_app_versions,id',
            'platform' => 'required|string|max:32',
            'channel' => 'nullable|string|max:32',
            'arch' => 'nullable|string|max:32',
            'version' => 'required|string|max:32',
            'build_number' => 'required|integer|min:1',
            'min_supported_build' => 'nullable|integer|min:0',
            'download_url' => 'required|url|max:2048',
            'file_size' => 'nullable|integer|min:0',
            'sha256' => 'nullable|string|size:64',
            'release_notes' => 'nullable|string',
            'is_force' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
            'published_at' => 'nullable|integer|min:0',
        ]);

        $data['platform'] = strtolower($data['platform']);
        $data['channel'] = strtolower($data['channel'] ?? 'stable');
        $data['arch'] = isset($data['arch']) && $data['arch'] !== ''
            ? strtolower($data['arch'])
            : null;
        $data['min_supported_build'] = (int) ($data['min_supported_build'] ?? 0);
        $data['is_force'] = (bool) ($data['is_force'] ?? false);
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['published_at'] = $data['published_at']
            ?? ($data['is_enabled'] ? time() : null);

        try {
            $version = empty($data['id'])
                ? AppVersion::create($data)
                : tap(AppVersion::findOrFail($data['id']))->update($data);
        } catch (\Throwable $e) {
            \Log::error($e);
            return $this->fail([500, '保存失败，请检查平台、渠道、架构和构建号是否重复']);
        }

        return $this->success($version);
    }

    public function publish(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:v2_app_versions,id']);
        $version = AppVersion::findOrFail($request->input('id'));
        $version->is_enabled = true;
        $version->published_at = $version->published_at ?: time();
        $version->save();

        return $this->success(true);
    }

    public function disable(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:v2_app_versions,id']);
        $version = AppVersion::findOrFail($request->input('id'));
        $version->is_enabled = false;
        $version->save();

        return $this->success(true);
    }

    public function drop(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:v2_app_versions,id']);
        AppVersion::findOrFail($request->input('id'))->delete();

        return $this->success(true);
    }
}
