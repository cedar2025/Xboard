<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <style>
        :root { --bg:#f6f8fb; --panel:#fff; --line:#e6ebf2; --text:#101828; --muted:#667085; --primary:#10b981; --danger:#ef4444; --warn:#f59e0b; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; background:var(--bg); color:var(--text); }
        a { color:inherit; text-decoration:none; }
        .shell { display:flex; min-height:100vh; }
        .side { width:240px; background:#101828; color:#d0d5dd; padding:24px 16px; }
        .brand { color:#fff; font-weight:800; font-size:18px; margin-bottom:28px; }
        .nav a { display:block; padding:11px 12px; border-radius:8px; margin-bottom:4px; }
        .nav a:hover, .nav a.active { background:#1d2939; color:#fff; }
        .main { flex:1; min-width:0; }
        .top { height:64px; background:var(--panel); border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; padding:0 28px; }
        .content { padding:28px; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:20px; box-shadow:0 8px 24px rgba(16,24,40,.04); }
        .grid { display:grid; gap:16px; }
        .grid-4 { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .grid-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .stat { font-size:28px; font-weight:800; margin-top:6px; }
        .muted { color:var(--muted); }
        .btn { display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--line); background:#fff; padding:9px 14px; border-radius:8px; cursor:pointer; font-weight:700; }
        .btn.primary { background:var(--primary); border-color:var(--primary); color:#fff; }
        .btn.danger { background:var(--danger); border-color:var(--danger); color:#fff; }
        .btn.warn { background:var(--warn); border-color:var(--warn); color:#fff; }
        table { width:100%; border-collapse:collapse; }
        th, td { border-bottom:1px solid var(--line); padding:12px 10px; text-align:left; vertical-align:top; }
        th { color:var(--muted); font-size:12px; text-transform:uppercase; }
        input, select, textarea { width:100%; border:1px solid var(--line); border-radius:8px; padding:10px 12px; font:inherit; background:#fff; }
        label { display:block; font-weight:700; margin:14px 0 6px; }
        textarea { min-height:140px; resize:vertical; }
        .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .badge { display:inline-block; padding:3px 8px; border-radius:999px; background:#eef2ff; color:#344054; font-size:12px; font-weight:700; }
        .badge.ok { background:#dcfce7; color:#166534; }
        .badge.off { background:#fee2e2; color:#991b1b; }
        .badge.warn { background:#fef3c7; color:#92400e; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
        .alert.ok { background:#dcfce7; color:#166534; }
        .alert.err { background:#fee2e2; color:#991b1b; }
        .actions { display:flex; gap:8px; align-items:center; }
        .actions form { margin:0; }
        @media (max-width: 980px) { .shell { display:block; } .side { width:100%; } .grid-4,.grid-2 { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="brand">App Distribution</div>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">首页看板</a>
            <a href="{{ route('apps.index') }}" class="{{ request()->routeIs('apps.*') ? 'active' : '' }}">App 管理</a>
            <a href="{{ route('versions.index') }}" class="{{ request()->routeIs('versions.*') ? 'active' : '' }}">版本管理</a>
            <a href="{{ route('devices.index') }}" class="{{ request()->routeIs('devices.*') ? 'active' : '' }}">设备统计</a>
            <a href="{{ route('logs.downloads') }}" class="{{ request()->routeIs('logs.downloads') ? 'active' : '' }}">下载日志</a>
            @if(auth()->user()?->hasAnyRole(['owner','admin']))
                <a href="{{ route('logs.audits') }}" class="{{ request()->routeIs('logs.audits') ? 'active' : '' }}">审计日志</a>
            @endif
        </nav>
    </aside>
    <main class="main">
        <header class="top">
            <strong>@yield('title', '后台')</strong>
            <div class="row">
                <a class="muted" href="{{ route('profile') }}">{{ auth()->user()->name }} · {{ auth()->user()->role }}</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn" type="submit">退出</button></form>
            </div>
        </header>
        <section class="content">
            @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
            @if($errors->any())
                <div class="alert err">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @yield('content')
        </section>
    </main>
</div>
</body>
</html>
