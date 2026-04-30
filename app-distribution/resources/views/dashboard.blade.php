@extends('layouts.app')
@section('title','首页看板')
@section('content')
<div class="grid grid-4">
    <div class="card"><div class="muted">App 数量</div><div class="stat">{{ $stats['apps'] }}</div></div>
    <div class="card"><div class="muted">已发布版本</div><div class="stat">{{ $stats['published_versions'] }}</div></div>
    <div class="card"><div class="muted">总下载量</div><div class="stat">{{ $stats['downloads'] }}</div></div>
    <div class="card"><div class="muted">7 日活跃设备</div><div class="stat">{{ $stats['active_7d'] }}</div></div>
</div>
<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h3>最近版本</h3>
        <table>
            <thead><tr><th>App</th><th>平台</th><th>版本</th><th>状态</th></tr></thead>
            <tbody>
            @foreach($latestVersions as $version)
                <tr>
                    <td>{{ $version->app->name }}</td>
                    <td>{{ $version->platform }} / {{ $version->channel }}</td>
                    <td>v{{ $version->version }} ({{ $version->build_number }})</td>
                    <td><span class="badge {{ $version->status === 'published' ? 'ok' : ($version->status === 'disabled' ? 'off' : 'warn') }}">{{ $version->status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3>设备平台分布</h3>
        <table>
            <thead><tr><th>平台</th><th>设备数</th></tr></thead>
            <tbody>
            @foreach($platforms as $row)<tr><td>{{ $row->platform }}</td><td>{{ $row->total }}</td></tr>@endforeach
            </tbody>
        </table>
        <h3>近 7 日更新事件</h3>
        <table>
            <thead><tr><th>事件</th><th>数量</th></tr></thead>
            <tbody>
            @foreach($events as $row)<tr><td>{{ $row->event }}</td><td>{{ $row->total }}</td></tr>@endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
