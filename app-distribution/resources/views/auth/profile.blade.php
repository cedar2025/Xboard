@extends('layouts.app')
@section('title','账号设置')
@section('content')
<div class="card" style="max-width:560px">
    <h2>修改密码</h2>
    <form method="post" action="{{ route('profile.password') }}">
        @csrf
        <label>当前密码</label>
        <input type="password" name="current_password" required>
        <label>新密码</label>
        <input type="password" name="password" required minlength="10">
        <label>确认新密码</label>
        <input type="password" name="password_confirmation" required minlength="10">
        <div style="margin-top:18px"><button class="btn primary" type="submit">保存</button></div>
    </form>
</div>
@endsection
