@extends('layouts.app')
@section('title','App 管理')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:14px">
    <div class="muted">管理分发应用和 app_key</div>
    @if(auth()->user()->canManage())<a class="btn primary" href="{{ route('apps.create') }}">新建 App</a>@endif
</div>
<div class="card">
    <table>
        <thead><tr><th>ID</th><th>名称</th><th>App Key</th><th>版本数</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        @foreach($apps as $app)
            <tr>
                <td>{{ $app->id }}</td>
                <td><strong>{{ $app->name }}</strong><div class="muted">{{ $app->description }}</div></td>
                <td><code>{{ $app->app_key }}</code></td>
                <td>{{ $app->versions_count }}</td>
                <td><span class="badge {{ $app->is_active ? 'ok' : 'off' }}">{{ $app->is_active ? '启用' : '停用' }}</span></td>
                <td>@if(auth()->user()->canManage())<a class="btn" href="{{ route('apps.edit', $app) }}">编辑</a>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $apps->links() }}
</div>
@endsection
