<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ __('Subscribe') }}</title>
  <style>
    :root {
      --bg: #ffffff;
      --text: #000000;
      --text-secondary: #666666;
      --primary: #2196f3;
      --success: #4caf50;
      --danger: #f44336;
      --border: #eee;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      line-height: 1.6;
      background: var(--bg);
      color: var(--text);
      -webkit-font-smoothing: antialiased;
      padding: 2rem 1rem;
    }

    .container {
      max-width: 600px;
      margin: 0 auto;
    }

    .title {
      font-size: 1.75rem;
      font-weight: bold;
      margin-bottom: 2rem;
    }

    .info-list {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
      margin-bottom: 3rem;
    }

    .info-item {
      display: flex;
      align-items: center;
      font-size: 1rem;
    }

    .info-label {
      min-width: 100px;
      color: var(--text-secondary);
    }

    .info-value {
      color: var(--text);
      flex: 1;
      font-weight: 500;
      margin-left: 1rem;
    }

    .status {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 4px;
      font-size: 0.875rem;
      font-weight: 500;
    }

    .status.active {
      background: var(--success);
      color: white;
    }

    .status.inactive {
      background: var(--danger);
      color: white;
    }

    .links-section {
      margin-top: 2rem;
    }

    .links-title {
      font-size: 1.25rem;
      font-weight: bold;
      margin-bottom: 1rem;
      color: var(--text-secondary);
    }

    .link-item {
      position: relative;
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
    }

    .link-input {
      flex: 1;
      width: 100%;
      padding: 0.75rem;
      padding-right: 4rem;
      border: 1px solid var(--border);
      border-radius: 4px;
      font-size: 0.875rem;
      color: var(--text);
      background: #f5f5f5;
    }

    .link-input:focus {
      outline: none;
      border-color: var(--primary);
    }

    .copy-btn {
      position: absolute;
      right: 0.5rem;
      top: 50%;
      transform: translateY(-50%);
      padding: 0.5rem;
      border: none;
      background: none;
      color: var(--text-secondary);
      cursor: pointer;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
      transition: color 0.2s;
    }

    .copy-btn:hover {
      color: var(--primary);
    }

    .copy-btn svg {
      width: 1rem;
      height: 1rem;
    }

    .copy-btn.copied {
      color: var(--success);
    }

    .qr-section {
      display: flex;
      justify-content: center;
      margin-top: 2rem;
    }

    .qr-section img {
      width: 180px;
      height: 180px;
      padding: 0.75rem;
      background: white;
      border-radius: 4px;
    }

    @media (max-width: 640px) {
      body {
        padding: 1.5rem 1rem;
      }

      .title {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
      }

      .info-item {
        font-size: 0.875rem;
      }

      .info-label {
        min-width: 80px;
      }

      .qr-section img {
        width: 160px;
        height: 160px;
      }
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --bg: #000000;
        --text: #ffffff;
        --text-secondary: #999999;
        --border: #222;
      }

      .link-input {
        background: #111;
        border-color: var(--border);
        color: var(--text);
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <h1 class="title">{{ __('User Information') }}</h1>

    <div class="info-list">
      <div class="info-item">
        <div class="info-label">{{ __('Username') }}</div>
        <div class="info-value">{{ $username }}</div>
      </div>

      <div class="info-item">
        <div class="info-label">{{ __('Status') }}</div>
        <div class="info-value">
          <span class="status {{ $status }}">
            {{ $status === 'active' ? __('Active') : __('Inactive') }}
          </span>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">{{ __('Data Used') }}</div>
        <div class="info-value">{{ $data_used }}</div>
      </div>

      <div class="info-item">
        <div class="info-label">{{ __('Data Limit') }}</div>
        <div class="info-value">{{ $data_limit }}</div>
      </div>

      <div class="info-item">
        <div class="info-label">{{ __('Expiration Date') }}</div>
        <div class="info-value">{{ $expired_date }}</div>
      </div>

      @if (isset($device_limit))
        <div class="info-item">
          <div class="info-label">{{ __('Device Limit') }}</div>
          <div class="info-value">{{ $device_limit }} {{ __('Devices') }}</div>
        </div>
      @endif

      @if ($reset_day)
        <div class="info-item">
          <div class="info-label">{{ __('Reset In') }}</div>
          <div class="info-value">{{ $reset_day }} {{ __('Days') }}</div>
        </div>
      @endif
    </div>

    <div class="links-section">
      <h2 class="links-title">{{ __('Subscription Link') }}</h2>
      <div class="link-item">
        <input type="text" value="{{ $subscription_url }}" readonly id="sub_url" class="link-input" onclick="this.select()">
        <button class="copy-btn" onclick="copyToClipboard('sub_url')" title="{{ __('Copy') }}">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
          </svg>
          <span>{{ __('Copy') }}</span>
        </button>
      </div>
      <div class="qr-section">
        <img src="data:image/svg+xml;base64,{{ $qr_code }}" alt="{{ __('QR Code') }}">
      </div>
    </div>
  </div>

  <script>
    function copyToClipboard(elementId) {
      const element = document.getElementById(elementId);
      element.select();
      document.execCommand('copy');
      element.blur();

      const btn = element.nextElementSibling;
      const span = btn.querySelector('span');
      const originalText = span.textContent;

      btn.classList.add('copied');
      span.textContent = '{{ __('Copied') }}';

      setTimeout(() => {
        btn.classList.remove('copied');
        span.textContent = originalText;
      }, 1000);
    }
  </script>
</body>

</html>
