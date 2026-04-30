<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use App\Models\DistributionApp;
use App\Services\ArtifactStorage;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VersionController extends Controller
{
    public function index(Request $request)
    {
        $versions = AppVersion::with(['app', 'artifact'])
            ->when($request->input('app_id'), fn ($query, $appId) => $query->where('app_id', $appId))
            ->when($request->input('platform'), fn ($query, $platform) => $query->where('platform', $platform))
            ->when($request->input('channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $apps = DistributionApp::orderBy('name')->get();

        return view('versions.index', compact('versions', 'apps'));
    }

    public function create()
    {
        abort_unless(request()->user()->canManage(), 403);
        return view('versions.form', [
            'version' => new AppVersion(['channel' => 'stable', 'status' => AppVersion::STATUS_DRAFT]),
            'apps' => DistributionApp::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ArtifactStorage $storage, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->canManage(), 403);
        $data = $this->validatedVersionData($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = AppVersion::STATUS_DRAFT;
        $data['arch'] = $data['arch'] ?: null;
        $data['min_supported_build'] = (int) ($data['min_supported_build'] ?? 0);
        $data['is_force'] = $request->boolean('is_force');

        $version = AppVersion::create($data);
        if ($request->hasFile('artifact')) {
            $storage->store($version, $request->file('artifact'), $request->user()->id);
        }
        $auditLogger->log('version.create', $version, $data, $request);

        return redirect()->route('versions.index')->with('status', '版本草稿已创建');
    }

    public function edit(AppVersion $version)
    {
        abort_unless(request()->user()->canManage(), 403);
        return view('versions.form', [
            'version' => $version->load('artifact'),
            'apps' => DistributionApp::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AppVersion $version, ArtifactStorage $storage, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->canManage(), 403);
        if ($version->isPublished() && !$request->user()->hasAnyRole(['owner', 'admin'])) {
            abort(403);
        }

        $data = $this->validatedVersionData($request, $version);
        $data['arch'] = $data['arch'] ?: null;
        $data['min_supported_build'] = (int) ($data['min_supported_build'] ?? 0);
        $data['is_force'] = $request->boolean('is_force');
        $version->update($data);

        if ($request->hasFile('artifact')) {
            $storage->store($version, $request->file('artifact'), $request->user()->id);
        }
        $auditLogger->log('version.update', $version, $data, $request);

        return redirect()->route('versions.index')->with('status', '版本已更新');
    }

    public function publish(Request $request, AppVersion $version, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->canManage(), 403);
        if (!$version->artifact) {
            return back()->withErrors(['artifact' => '发布前必须上传安装包']);
        }

        $version->update([
            'status' => AppVersion::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        $auditLogger->log('version.publish', $version, [], $request);

        return back()->with('status', '版本已发布');
    }

    public function disable(Request $request, AppVersion $version, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->canManage(), 403);
        $version->update(['status' => AppVersion::STATUS_DISABLED]);
        $auditLogger->log('version.disable', $version, [], $request);

        return back()->with('status', '版本已下架');
    }

    public function destroy(Request $request, AppVersion $version, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->canManage(), 403);
        if ($version->isPublished()) {
            return back()->withErrors(['version' => '已发布版本不能物理删除，请先下架']);
        }

        $auditLogger->log('version.delete', $version, [], $request);
        $version->delete();

        return back()->with('status', '草稿版本已删除');
    }

    private function validatedVersionData(Request $request, ?AppVersion $version = null): array
    {
        return $request->validate([
            'app_id' => ['required', 'exists:apps,id'],
            'platform' => ['required', Rule::in(['macos', 'windows'])],
            'channel' => ['required', Rule::in(['stable', 'beta'])],
            'arch' => ['nullable', 'string', 'max:32'],
            'version' => ['required', 'string', 'max:32'],
            'build_number' => ['required', 'integer', 'min:1'],
            'min_supported_build' => ['nullable', 'integer', 'min:0'],
            'release_notes' => ['nullable', 'string', 'max:20000'],
            'is_force' => ['nullable', 'boolean'],
            'artifact' => [$version ? 'nullable' : 'required', 'file', 'max:2097152'],
        ]);
    }
}
