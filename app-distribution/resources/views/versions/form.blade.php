@extends('layouts.app')
@section('title', $version->exists ? '编辑版本' : '上传版本')
@section('content')
<div class="card" style="max-width:860px">
    <form method="post" enctype="multipart/form-data" action="{{ $version->exists ? route('versions.update', $version) : route('versions.store') }}">
        @csrf
        @if($version->exists) @method('PUT') @endif
        <div class="grid grid-2">
            <div>
                <label>App</label>
                <select name="app_id" required>
                    @foreach($apps as $app)<option value="{{ $app->id }}" @selected(old('app_id', $version->app_id) == $app->id)>{{ $app->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label>平台</label>
                <select name="platform" required>
                    <option value="macos" @selected(old('platform', $version->platform)==='macos')>macOS</option>
                    <option value="windows" @selected(old('platform', $version->platform)==='windows')>Windows</option>
                </select>
            </div>
            <div>
                <label>渠道</label>
                <select name="channel" required>
                    <option value="stable" @selected(old('channel', $version->channel)==='stable')>stable</option>
                    <option value="beta" @selected(old('channel', $version->channel)==='beta')>beta</option>
                </select>
            </div>
            <div>
                <label>架构</label>
                <input name="arch" value="{{ old('arch', $version->arch) }}" placeholder="留空表示 universal">
            </div>
            <div>
                <label>版本号</label>
                <input name="version" value="{{ old('version', $version->version) }}" placeholder="1.0.1" required>
            </div>
            <div>
                <label>构建号</label>
                <input type="number" min="1" name="build_number" value="{{ old('build_number', $version->build_number) }}" required>
            </div>
            <div>
                <label>最低支持 build</label>
                <input type="number" min="0" name="min_supported_build" value="{{ old('min_supported_build', $version->min_supported_build ?? 0) }}">
            </div>
            <div>
                <label>安装包</label>
                <input type="file" name="artifact" {{ $version->exists ? '' : 'required' }}>
                @if($version->artifact)<div class="muted">当前：{{ $version->artifact->original_name }} · {{ $version->artifact->sha256 }}</div>@endif
            </div>
        </div>
        <label class="row"><input style="width:auto" type="checkbox" name="is_force" value="1" {{ old('is_force', $version->is_force) ? 'checked' : '' }}> 强制更新</label>
        <label>更新说明</label>
        <textarea name="release_notes">{{ old('release_notes', $version->release_notes) }}</textarea>
        <div class="row" style="margin-top:18px">
            <button class="btn primary" type="submit">保存</button>
            <a class="btn" href="{{ route('versions.index') }}">返回</a>
        </div>
    </form>
</div>
@endsection
