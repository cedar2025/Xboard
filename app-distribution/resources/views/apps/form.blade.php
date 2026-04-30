@extends('layouts.app')
@section('title', $app->exists ? '编辑 App' : '新建 App')
@section('content')
<div class="card" style="max-width:720px">
    <form method="post" action="{{ $app->exists ? route('apps.update', $app) : route('apps.store') }}">
        @csrf
        @if($app->exists) @method('PUT') @endif
        <label>名称</label>
        <input name="name" value="{{ old('name', $app->name) }}" required>
        <label>App Key</label>
        <input name="app_key" value="{{ old('app_key', $app->app_key) }}" placeholder="留空自动生成" {{ $app->exists ? 'required' : '' }}>
        <label>描述</label>
        <textarea name="description">{{ old('description', $app->description) }}</textarea>
        <label class="row"><input style="width:auto" type="checkbox" name="is_active" value="1" {{ old('is_active', $app->exists ? $app->is_active : true) ? 'checked' : '' }}> 启用</label>
        <div class="row" style="margin-top:18px">
            <button class="btn primary" type="submit">保存</button>
            <a class="btn" href="{{ route('apps.index') }}">返回</a>
        </div>
    </form>
</div>
@endsection
