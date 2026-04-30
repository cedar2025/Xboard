@extends('layouts.app')
@section('title','版本管理')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:14px">
    <form class="row" method="get">
        <select name="app_id" style="width:220px">
            <option value="">全部 App</option>
            @foreach($apps as $app)<option value="{{ $app->id }}" @selected(request('app_id') == $app->id)>{{ $app->name }}</option>@endforeach
        </select>
        <select name="platform" style="width:140px">
            <option value="">全部平台</option>
            <option value="macos" @selected(request('platform')==='macos')>macOS</option>
            <option value="windows" @selected(request('platform')==='windows')>Windows</option>
        </select>
        <select name="channel" style="width:140px">
            <option value="">全部渠道</option>
            <option value="stable" @selected(request('channel')==='stable')>stable</option>
            <option value="beta" @selected(request('channel')==='beta')>beta</option>
        </select>
        <button class="btn" type="submit">筛选</button>
    </form>
    @if(auth()->user()->canManage())<a class="btn primary" href="{{ route('versions.create') }}">上传版本</a>@endif
</div>
<div class="card">
    <table>
        <thead><tr><th>ID</th><th>App</th><th>平台/渠道</th><th>版本</th><th>安装包</th><th>策略</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        @foreach($versions as $version)
            <tr>
                <td>{{ $version->id }}</td>
                <td>{{ $version->app->name }}</td>
                <td>{{ $version->platform }} / {{ $version->channel }} @if($version->arch)<span class="badge">{{ $version->arch }}</span>@endif</td>
                <td>v{{ $version->version }}<br><span class="muted">build {{ $version->build_number }}</span></td>
                <td>
                    @if($version->artifact)
                        {{ $version->artifact->original_name }}<br>
                        <span class="muted">{{ number_format($version->artifact->file_size / 1024 / 1024, 1) }} MB · {{ substr($version->artifact->sha256, 0, 12) }}...</span>
                    @else
                        <span class="badge off">未上传</span>
                    @endif
                </td>
                <td>
                    @if($version->is_force)<span class="badge off">强制</span>@endif
                    <span class="badge">min {{ $version->min_supported_build }}</span>
                </td>
                <td><span class="badge {{ $version->status === 'published' ? 'ok' : ($version->status === 'disabled' ? 'off' : 'warn') }}">{{ $version->status }}</span></td>
                <td class="actions">
                    @if(auth()->user()->canManage())
                        <a class="btn" href="{{ route('versions.edit', $version) }}">编辑</a>
                        @if($version->status !== 'published')
                            <form method="post" action="{{ route('versions.publish', $version) }}">@csrf<button class="btn primary" type="submit">发布</button></form>
                        @endif
                        @if($version->status === 'published')
                            <form method="post" action="{{ route('versions.disable', $version) }}">@csrf<button class="btn warn" type="submit">下架</button></form>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $versions->links() }}
</div>
@endsection
