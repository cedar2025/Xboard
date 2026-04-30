<?php

namespace App\Http\Controllers;

use App\Models\DistributionApp;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function index()
    {
        $apps = DistributionApp::withCount('versions')->orderByDesc('id')->paginate(20);
        return view('apps.index', compact('apps'));
    }

    public function create()
    {
        abort_unless(request()->user()->canManage(), 403);
        return view('apps.form', ['app' => new DistributionApp()]);
    }

    public function store(Request $request, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->canManage(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'app_key' => ['nullable', 'string', 'max:64', 'unique:apps,app_key'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['app_key'] = $data['app_key'] ?: Str::slug($data['name']) . '-' . Str::random(8);
        $data['is_active'] = $request->boolean('is_active', true);
        $app = DistributionApp::create($data);
        $auditLogger->log('app.create', $app, $data, $request);

        return redirect()->route('apps.index')->with('status', 'App 已创建');
    }

    public function edit(DistributionApp $app)
    {
        abort_unless(request()->user()->canManage(), 403);
        return view('apps.form', compact('app'));
    }

    public function update(Request $request, DistributionApp $app, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->canManage(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'app_key' => ['required', 'string', 'max:64', 'unique:apps,app_key,' . $app->id],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $app->update($data);
        $auditLogger->log('app.update', $app, $data, $request);

        return redirect()->route('apps.index')->with('status', 'App 已更新');
    }
}
