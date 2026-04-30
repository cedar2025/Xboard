<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 - {{ config('app.name') }}</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#f6f8fb; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; color:#101828; }
        .card { width:min(420px, calc(100vw - 32px)); background:#fff; border:1px solid #e6ebf2; border-radius:14px; padding:28px; box-shadow:0 18px 50px rgba(16,24,40,.08); }
        h1 { margin:0 0 8px; }
        p { color:#667085; margin:0 0 24px; }
        label { display:block; font-weight:700; margin:14px 0 6px; }
        input { width:100%; border:1px solid #e6ebf2; border-radius:8px; padding:11px 12px; font:inherit; }
        button { width:100%; margin-top:22px; border:0; background:#10b981; color:#fff; border-radius:8px; padding:12px; font-weight:800; cursor:pointer; }
        .err { background:#fee2e2; color:#991b1b; padding:10px 12px; border-radius:8px; margin-bottom:14px; }
    </style>
</head>
<body>
<form class="card" method="post" action="{{ route('login.post') }}">
    @csrf
    <h1>App 分发后台</h1>
    <p>使用管理员账号登录</p>
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <label>邮箱</label>
    <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
    <label>密码</label>
    <input name="password" type="password" autocomplete="current-password" required>
    <button type="submit">登录</button>
</form>
</body>
</html>
