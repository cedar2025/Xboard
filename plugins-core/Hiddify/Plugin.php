<?php

namespace Plugin\Hiddify;

use App\Models\Order;
use App\Models\User;
use App\Services\Plugin\AbstractPlugin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        $this->listen('order.open.after', [$this, 'handleOrderOpened']);
        $this->filter('user.subscribe.response', [$this, 'filterSubscribeResponse']);
    }

    public function handleOrderOpened(Order $order): void
    {
        $config = $this->getConfig();

        if (! (bool) ($config['auto_sale_enable'] ?? false)) {
            return;
        }

        if (empty($config['admin_url']) || empty($config['admin_user']) || empty($config['admin_password'])) {
            Log::warning('[Hiddify] 插件未配置完整，跳过自动创建用户');
            return;
        }

        $user = $order->user;
        if (! $user || ! $user->plan_id) {
            return;
        }

        try {
            $subscribeUrl = $this->ensureHiddifyUser($user);
            if ($subscribeUrl) {
                $user->hiddify_subscribe_url = $subscribeUrl;
                $user->save();
            }
        } catch (Throwable $exception) {
            Log::error('[Hiddify] 自动创建用户失败：' . $exception->getMessage(), [
                'user_id' => $user->id,
                'order_id' => $order->id,
            ]);
        }
    }

    public function filterSubscribeResponse(mixed $payload): mixed
    {
        if ($payload instanceof User) {
            if (! empty($payload->hiddify_subscribe_url)) {
                $payload['subscribe_url'] = $payload->hiddify_subscribe_url;
            }
            return $payload;
        }

        if (is_array($payload) && ! empty($payload['id'])) {
            $user = User::find($payload['id']);
            if ($user && ! empty($user->hiddify_subscribe_url)) {
                $payload['subscribe_url'] = $user->hiddify_subscribe_url;
            }
        }

        return $payload;
    }

    protected function ensureHiddifyUser(User $user): ?string
    {
        if (! empty($user->hiddify_subscribe_url)) {
            return $user->hiddify_subscribe_url;
        }

        $token = $this->authenticate();
        if (! $token) {
            return null;
        }

        $result = $this->createHiddifyUser($user, $token);
        if (empty($result)) {
            return null;
        }

        $subscribeUrl = $this->resolveSubscribeUrl($result, $user);
        if (! empty($subscribeUrl)) {
            $user->hiddify_user_id = $this->arrayGet($result, 'id') ?? $this->arrayGet($result, 'user_id');
            $user->hiddify_subscribe_url = $subscribeUrl;
            $user->save();
        }

        return $subscribeUrl;
    }

    protected function authenticate(): ?string
    {
        $adminUrl = rtrim($this->getConfig('admin_url'), '/');
        $adminUser = $this->getConfig('admin_user');
        $adminPassword = $this->getConfig('admin_password');

        $payload = [
            'password' => $adminPassword,
        ];

        if (filter_var($adminUser, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $adminUser;
        } else {
            $payload['username'] = $adminUser;
        }

        $paths = ['/api/v1/auth/login', '/api/v1/login'];
        foreach ($paths as $path) {
            $response = Http::acceptJson()
                ->withOptions(['verify' => (bool) ($this->getConfig('verify_ssl') ?? false)])
                ->post($adminUrl . $path, $payload);

            if ($response->status() === 404) {
                continue;
            }

            if (! $response->successful()) {
                if ($response->status() === 401 || $response->status() === 422) {
                    break;
                }
                continue;
            }

            $data = $response->json();
            $token = $this->arrayGet($data, 'access_token')
                ?? $this->arrayGet($data, 'token')
                ?? $this->arrayGet($data, 'data.token')
                ?? $this->arrayGet($data, 'data.access_token');

            if ($token) {
                return $token;
            }
        }

        throw new \RuntimeException('Hiddify 登录失败，无法获取访问令牌。');
    }

    protected function createHiddifyUser(User $user, string $token): ?array
    {
        $adminUrl = rtrim($this->getConfig('admin_url'), '/');
        $username = $this->buildHiddifyUsername($user);
        $password = Str::random(20);

        $payload = array_filter([
            'username' => $username,
            'password' => $password,
            'email' => $user->email ?: $username . '@example.com',
            'expired_at' => $user->expired_at ? date('c', $user->expired_at) : null,
            'transfer_enable' => $this->convertTransferEnableToBytes($user->transfer_enable),
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::withToken($token)
            ->acceptJson()
            ->withOptions(['verify' => (bool) ($this->getConfig('verify_ssl') ?? false)])
            ->post($adminUrl . '/api/v1/users', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        if ($response->status() === 409) {
            return $this->fetchExistingHiddifyUser($adminUrl, $token, $username);
        }

        throw new \RuntimeException('Hiddify 创建用户失败：' . $response->body());
    }

    protected function convertTransferEnableToBytes(mixed $transferEnable): ?int
    {
        if (empty($transferEnable)) {
            return null;
        }

        return (int) max(0, $transferEnable * 1024);
    }

    protected function fetchExistingHiddifyUser(string $adminUrl, string $token, string $username): ?array
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->withOptions(['verify' => (bool) ($this->getConfig('verify_ssl') ?? false)])
            ->get($adminUrl . '/api/v1/users', ['username' => $username]);

        if ($response->successful()) {
            $data = $response->json();
            if (is_array($data['data'] ?? $data)) {
                $items = $data['data'] ?? $data;
                if (! empty($items)) {
                    return $items[0];
                }
            }
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->withOptions(['verify' => (bool) ($this->getConfig('verify_ssl') ?? false)])
            ->get($adminUrl . '/api/v1/users/' . urlencode($username));

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    protected function resolveSubscribeUrl(array $result, User $user): ?string
    {
        $adminUrl = rtrim($this->getConfig('admin_url'), '/');
        $template = $this->getConfig('subscribe_path_template') ?: '/sub/{username}';
        $username = $this->buildHiddifyUsername($user);

        foreach (['subscribe_url', 'link', 'url', 'data.url', 'data.link'] as $key) {
            $value = $this->arrayGet($result, $key);
            if (! empty($value)) {
                return (string) $value;
            }
        }

        $replacements = [
            '{admin_url}' => $adminUrl,
            '{username}' => $username,
            '{id}' => $this->arrayGet($result, 'id') ?: $username,
        ];

        $path = str_replace(array_keys($replacements), array_values($replacements), $template);
        return rtrim($adminUrl, '/') . '/' . ltrim($path, '/');
    }

    protected function buildHiddifyUsername(User $user): string
    {
        $emailUser = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($user->email ?? ''));
        if (empty($emailUser)) {
            $emailUser = 'user' . $user->id;
        }

        return 'xboard_' . $user->id . '_' . Str::slug($emailUser, '_');
    }

    protected function arrayGet(array $array, string $key): mixed
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? null;
        }

        $segments = explode('.', $key);
        $value = $array;
        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
