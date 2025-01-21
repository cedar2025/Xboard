<?php

namespace App\Services\Plugin;

abstract class AbstractPlugin
{
    protected array $config = [];
    
    /**
     * 插件启动时调用
     */
    public function boot(): void
    {
        // 子类实现具体逻辑
    }

    /**
     * 插件禁用时调用
     */
    public function cleanup(): void
    {
        // 子类实现具体逻辑
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * 获取配置
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 注册事件监听器
     */
    protected function listen(string $hook, callable $callback): void
    {
        HookManager::register($hook, $callback);
    }

    /**
     * 移除事件监听器
     */
    protected function removeListener(string $hook): void
    {
        HookManager::remove($hook);
    }
} 