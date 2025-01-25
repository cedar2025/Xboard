<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class PluginManager
{
    protected string $pluginPath;

    public function __construct()
    {
        $this->pluginPath = base_path('plugins');
    }

    /**
     * 安装插件
     */
    public function install(string $pluginCode): bool
    {
        $configFile = $this->pluginPath . '/' . $pluginCode . '/config.json';

        if (!File::exists($configFile)) {
            throw new \Exception('Plugin config file not found');
        }

        $config = json_decode(File::get($configFile), true);
        if (!$this->validateConfig($config)) {
            throw new \Exception('Invalid plugin config');
        }

        // 检查依赖
        if (!$this->checkDependencies($config['require'] ?? [])) {
            throw new \Exception('Dependencies not satisfied');
        }

        // 提取配置默认值
        $defaultValues = [];
        if (isset($config['config']) && is_array($config['config'])) {
            foreach ($config['config'] as $key => $item) {
                $defaultValues[$key] = $item['default'] ?? null;
            }
        }

        // 注册到数据库
        Plugin::create([
            'code' => $pluginCode,
            'name' => $config['name'],
            'version' => $config['version'],
            'is_enabled' => false,
            'config' => json_encode($defaultValues),
            'installed_at' => now(),
        ]);

        return true;
    }

    /**
     * 启用插件
     */
    public function enable(string $pluginCode): bool
    {
        $plugin = $this->loadPlugin($pluginCode);
        if (!$plugin) {
            throw new \Exception('Plugin not found');
        }

        // 获取插件配置
        $dbPlugin = Plugin::query()
            ->where('code', $pluginCode)
            ->first();

        if ($dbPlugin && !empty($dbPlugin->config)) {
            $plugin->setConfig(json_decode($dbPlugin->config, true));
        }

        // 更新数据库状态
        Plugin::query()
            ->where('code', $pluginCode)
            ->update([
                'is_enabled' => true,
                'updated_at' => now(),
            ]);

        // 加载路由
        $routesFile = $this->pluginPath . '/' . $pluginCode . '/routes/web.php';
        if (File::exists($routesFile)) {
            require $routesFile;
        }
        // 注册视图
        $viewsPath = $this->pluginPath . '/' . $pluginCode . '/resources/views';
        if (File::exists($viewsPath)) {
            View::addNamespace($pluginCode, $viewsPath);
        }

        // 初始化插件
        if (method_exists($plugin, 'boot')) {
            $plugin->boot();
        }

        return true;
    }

    /**
     * 禁用插件
     */
    public function disable(string $pluginCode): bool
    {
        $plugin = $this->loadPlugin($pluginCode);
        if (!$plugin) {
            throw new \Exception('Plugin not found');
        }

        // 更新数据库状态
        Plugin::query()
            ->where('code', $pluginCode)
            ->update([
                'is_enabled' => false,
                'updated_at' => now(),
            ]);

        // 清理插件
        if (method_exists($plugin, 'cleanup')) {
            $plugin->cleanup();
        }

        return true;
    }

    /**
     * 卸载插件
     */
    public function uninstall(string $pluginCode): bool
    {
        // 先禁用插件
        $this->disable($pluginCode);

        // 删除数据库记录
        Plugin::query()->where('code', $pluginCode)->delete();

        return true;
    }

    /**
     * 加载插件实例
     */
    protected function loadPlugin(string $pluginCode)
    {
        $pluginFile = $this->pluginPath . '/' . $pluginCode . '/Plugin.php';
        if (!File::exists($pluginFile)) {
            return null;
        }

        require_once $pluginFile;
        $className = "Plugin\\{$pluginCode}\\Plugin";
        return new $className($pluginCode);
    }

    /**
     * 验证配置文件
     */
    protected function validateConfig(array $config): bool
    {
        return isset($config['code'])
            && isset($config['version'])
            && isset($config['description'])
            && isset($config['author']);
    }

    /**
     * 检查依赖关系
     */
    protected function checkDependencies(array $requires): bool
    {
        foreach ($requires as $package => $version) {
            if ($package === 'xboard') {
                // 检查xboard版本
                // 实现版本比较逻辑
            }
        }
        return true;
    }
}