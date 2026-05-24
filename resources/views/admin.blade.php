<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  <script>
    window.settings = {
      base_url: "/",
      title: "{{ $title }}",
      version: "{{ $version }}",
      logo: "{{ $logo }}",
      secure_path: "{{ $secure_path }}",
    };
  </script>
  <script type="module" crossorigin src="/assets/admin/assets/index.js"></script>
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/index.css" />
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/vendor.css">
  <script src="/assets/admin/locales/en-US.js"></script>
  <script src="/assets/admin/locales/zh-CN.js"></script>
  <script src="/assets/admin/locales/ko-KR.js"></script>
  <style>
    .xboard-app-downloads-entry {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 2147483000;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 36px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      border-radius: 6px;
      padding: 0 12px;
      color: #fff;
      background: #0f172a;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
      font: 500 13px/1.2 Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      text-decoration: none;
    }
    .xboard-app-downloads-entry:hover {
      background: #1e293b;
    }
  </style>
</head>

<body>
  <div id="root"></div>
  <a class="xboard-app-downloads-entry" href="/{{ $secure_path }}/app-downloads">App 下载管理</a>
</body>

</html>
