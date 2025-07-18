<?php
use App\Support\Setting;
use Illuminate\Support\Facades\App;

if (!function_exists('admin_setting')) {
    /**
     * 获取或保存配置参数.
     *
     * @param  string|array  $key
     * @param  mixed  $default
     * @return App\Support\Setting|mixed
     */
    function admin_setting($key = null, $default = null)
    {
        $setting = app(Setting::class);

        if ($key === null) {
            return $setting->toArray();
        }

        if (is_array($key)) {
            $setting->save($key);
            return '';
        }

        $default = config('v2board.' . $key) ?? $default;
        return $setting->get($key) ?? $default;
    }
}

if (!function_exists('admin_settings_batch')) {
    /**
     * 批量获取配置参数，性能优化版本
     *
     * @param array $keys 配置键名数组
     * @return array 返回键值对数组
     */
    function admin_settings_batch(array $keys): array
    {
        return app(Setting::class)->getBatch($keys);
    }
}

if (!function_exists('origin_url')) {
    /**
     * 根据 HTTP_ORIGIN 拼接完整 URL
     * @param string $path
     * @return string
     */
    function origin_url(string $path = ''): string
    {
        $origin = request()->getSchemeAndHttpHost(); // 自动带端口
        $origin = rtrim($origin, '/');
        $path = ltrim($path, '/');
        return $origin . '/' . $path;
    }
}
