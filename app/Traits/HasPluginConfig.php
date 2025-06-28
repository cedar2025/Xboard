<?php

namespace App\Traits;

use App\Models\Plugin;
use Illuminate\Support\Facades\Cache;

trait HasPluginConfig
{
    /**
     * 缓存的插件配置
     */
    protected ?array $pluginConfig = null;

    /**
     * 插件代码
     */
    protected ?string $pluginCode = null;

    /**
     * 获取插件配置
     */
    public function getConfig(?string $key = null, $default = null): mixed
    {
        $config = $this->getPluginConfig();
        
        if ($key) {
            return $config[$key] ?? $default;
        }
        
        return $config;
    }

    /**
     * 获取完整的插件配置
     */
    protected function getPluginConfig(): array
    {
        if ($this->pluginConfig === null) {
            $pluginCode = $this->getPluginCode();

            \Log::channel('daily')->info('Telegram Login: 获取插件配置', [
                'plugin_code' => $pluginCode
            ]);

            $this->pluginConfig = Cache::remember(
                "plugin_config_{$pluginCode}",
                3600,
                function () use ($pluginCode) {
                    $plugin = Plugin::where('code', $pluginCode)
                        ->where('is_enabled', true)
                        ->first();

                    if (!$plugin || !$plugin->config) {
                        return [];
                    }

                    return json_decode($plugin->config, true) ?? [];
                }
            );
        }

        return $this->pluginConfig;
    }

    /**
     * 获取插件代码
     */
    public function getPluginCode(): string
    {
        if ($this->pluginCode === null) {
            $this->pluginCode = $this->autoDetectPluginCode();
        }

        return $this->pluginCode;
    }

    /**
     * 设置插件代码（如果自动检测不准确可以手动设置）
     */
    public function setPluginCode(string $pluginCode): void
    {
        $this->pluginCode = $pluginCode;
        $this->pluginConfig = null; // 重置配置缓存
    }

    /**
     * 自动检测插件代码
     */
    protected function autoDetectPluginCode(): string
    {
        $reflection = new \ReflectionClass($this);
        $namespace = $reflection->getNamespaceName();
        
        // 从命名空间提取插件代码
        // 例如: Plugin\TelegramLogin\Controllers => telegram_login
        if (preg_match('/^Plugin\\\\(.+?)\\\\/', $namespace, $matches)) {
            return $this->convertToKebabCase($matches[1]);
        }
        
        throw new \RuntimeException('Unable to detect plugin code from namespace: ' . $namespace);
    }

    /**
     * 将 StudlyCase 转换为 kebab-case
     */
    protected function convertToKebabCase(string $string): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }

    /**
     * 检查插件是否启用
     */
    public function isPluginEnabled(): bool
    {
        return (bool) $this->getConfig('enable', false);
    }

    /**
     * 清除插件配置缓存
     */
    public function clearConfigCache(): void
    {
        $pluginCode = $this->getPluginCode();
        Cache::forget("plugin_config_{$pluginCode}");
        $this->pluginConfig = null;
    }
} 