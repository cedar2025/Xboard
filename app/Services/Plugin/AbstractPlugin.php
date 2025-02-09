<?php

namespace App\Services\Plugin;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractPlugin
{
    protected array $config = [];
    protected string $basePath;
    protected string $pluginCode;
    protected string $namespace;

    public function __construct(string $pluginCode)
    {
        $this->pluginCode = $pluginCode;
        $this->namespace = 'Plugin\\' . Str::studly($pluginCode);
        $reflection = new \ReflectionClass($this);
        $this->basePath = dirname($reflection->getFileName());
    }

    /**
     * 获取插件代码
     */
    public function getPluginCode(): string
    {
        return $this->pluginCode;
    }

    /**
     * 获取插件命名空间
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * 获取插件基础路径
     */
    public function getBasePath(): string
    {
        return $this->basePath;
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
     * 获取视图
     */
    protected function view(string $view, array $data = [], array $mergeData = []): \Illuminate\Contracts\View\View
    {
        return view(Str::studly($this->pluginCode) . '::' . $view, $data, $mergeData);
    }

    /**
     * 注册动作钩子监听器
     */
    protected function listen(string $hook, callable $callback, int $priority = 20): void
    {
        HookManager::register($hook, $callback, $priority);
    }

    /**
     * 注册过滤器钩子
     */
    protected function filter(string $hook, callable $callback, int $priority = 20): void
    {
        HookManager::registerFilter($hook, $callback, $priority);
    }

    /**
     * 移除事件监听器
     */
    protected function removeListener(string $hook): void
    {
        HookManager::remove($hook);
    }

    /**
     * 中断当前请求并返回新的响应
     *
     * @param Response|string|array $response
     * @return never
     */
    protected function intercept(Response|string|array $response): never
    {
        HookManager::intercept($response);
    }

    /**
     * 插件启动时调用
     */
    public function boot(): void
    {
        // 插件启动时的初始化逻辑
    }

    /**
     * 插件安装时调用
     */
    public function install(): void
    {
        // 插件安装时的初始化逻辑
    }

    /**
     * 插件卸载时调用
     */
    public function uninstall(): void
    {
        // 插件卸载时的清理逻辑
    }

    /**
     * 插件更新时调用
     */
    public function update(string $oldVersion, string $newVersion): void
    {
        // 插件更新时的迁移逻辑
    }

    /**
     * 获取插件资源URL
     */
    protected function asset(string $path): string
    {
        return asset('plugins/' . $this->pluginCode . '/' . ltrim($path, '/'));
    }

    /**
     * 获取插件配置项
     */
    protected function getConfigValue(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 获取插件数据库迁移路径
     */
    protected function getMigrationsPath(): string
    {
        return $this->basePath . '/database/migrations';
    }

    /**
     * 获取插件视图路径
     */
    protected function getViewsPath(): string
    {
        return $this->basePath . '/resources/views';
    }

    /**
     * 获取插件资源路径
     */
    protected function getAssetsPath(): string
    {
        return $this->basePath . '/resources/assets';
    }
}