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
</head>

<body>
  <div id="root"></div>
</body>

</html>
