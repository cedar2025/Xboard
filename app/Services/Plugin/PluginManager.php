<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class PluginManager
{
    protected string $pluginPath;
    protected array $loadedPlugins = [];

    public function __construct()
    {
        $this->pluginPath = base_path('plugins');
    }

    /**
     * 获取插件的命名空间
     */
    public function getPluginNamespace(string $pluginCode): string
    {
        return 'Plugin\\' . Str::studly($pluginCode);
    }

    /**
     * 获取插件的基础路径
     */
    public function getPluginPath(string $pluginCode): string
    {
        return $this->pluginPath . '/' . Str::studly($pluginCode);
    }

    /**
     * 加载插件类
     */
    protected function loadPlugin(string $pluginCode)
    {
        if (isset($this->loadedPlugins[$pluginCode])) {
            return $this->loadedPlugins[$pluginCode];
        }

        $pluginClass = $this->getPluginNamespace($pluginCode) . '\\Plugin';

        if (!class_exists($pluginClass)) {
            $pluginFile = $this->getPluginPath($pluginCode) . '/Plugin.php';
            if (!File::exists($pluginFile)) {
                Log::error("Plugin class file not found: {$pluginFile}");
                return null;
            }
            require_once $pluginFile;
        }

        if (!class_exists($pluginClass)) {
            Log::error("Plugin class not found: {$pluginClass}");
            return null;
        }

        $plugin = new $pluginClass($pluginCode);
        $this->loadedPlugins[$pluginCode] = $plugin;

        return $plugin;
    }

    /**
     * 注册插件的服务提供者
     */
    protected function registerServiceProvider(string $pluginCode): void
    {
        $providerClass = $this->getPluginNamespace($pluginCode) . '\\Providers\\PluginServiceProvider';

        if (class_exists($providerClass)) {
            app()->register($providerClass);
        }
    }

    /**
     * 加载插件的路由
     */
    protected function loadRoutes(string $pluginCode): void
    {
        $routesPath = $this->getPluginPath($pluginCode) . '/routes';
        if (File::exists($routesPath)) {
            $webRouteFile = $routesPath . '/web.php';
            $apiRouteFile = $routesPath . '/api.php';
            if (File::exists($webRouteFile)) {
                Route::middleware('web')
                    ->namespace($this->getPluginNamespace($pluginCode) . '\\Controllers')
                    ->group(function () use ($webRouteFile) {
                        require $webRouteFile;
                    });
            }
            if (File::exists($apiRouteFile)) {
                Route::middleware('api')
                    ->namespace($this->getPluginNamespace($pluginCode) . '\\Controllers')
                    ->group(function () use ($apiRouteFile) {
                        require $apiRouteFile;
                    });
            }
        }
    }

    /**
     * 加载插件的视图
     */
    protected function loadViews(string $pluginCode): void
    {
        $viewsPath = $this->getPluginPath($pluginCode) . '/resources/views';

        if (File::exists($viewsPath)) {
            View::addNamespace(Str::studly($pluginCode), $viewsPath);
        }
    }

    /**
     * 安装插件
     */
    public function install(string $pluginCode): bool
    {
        DB::beginTransaction();
        try {
            $configFile = $this->getPluginPath($pluginCode) . '/config.json';

            if (!File::exists($configFile)) {
                throw new \Exception('Plugin config file not found');
            }

            $config = json_decode(File::get($configFile), true);
            if (!$this->validateConfig($config)) {
                throw new \Exception('Invalid plugin config');
            }

            // 检查插件是否已安装
            if (Plugin::where('code', $pluginCode)->exists()) {
                throw new \Exception('Plugin already installed');
            }

            // 检查依赖
            if (!$this->checkDependencies($config['require'] ?? [])) {
                throw new \Exception('Dependencies not satisfied');
            }

            // 运行数据库迁移
            $this->runMigrations($pluginCode);

            // 提取配置默认值
            $defaultValues = $this->extractDefaultConfig($config);

            // 创建插件实例
            $plugin = $this->loadPlugin($pluginCode);

            // 注册到数据库
            $dbPlugin = Plugin::create([
                'code' => $pluginCode,
                'name' => $config['name'],
                'version' => $config['version'],
                'is_enabled' => false,
                'config' => json_encode($defaultValues),
                'installed_at' => now(),
            ]);

            // 运行插件安装方法
            if (method_exists($plugin, 'install')) {
                $plugin->install();
            }

            // 发布插件资源
            $this->publishAssets($pluginCode);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 提取插件默认配置
     */
    protected function extractDefaultConfig(array $config): array
    {
        $defaultValues = [];
        if (isset($config['config']) && is_array($config['config'])) {
            foreach ($config['config'] as $key => $item) {
                if (is_array($item)) {
                    $defaultValues[$key] = $item['default'] ?? null;
                } else {
                    $defaultValues[$key] = $item;
                }
            }
        }
        return $defaultValues;
    }

    /**
     * 运行插件数据库迁移
     */
    protected function runMigrations(string $pluginCode): void
    {
        $migrationsPath = $this->getPluginPath($pluginCode) . '/database/migrations';

        if (File::exists($migrationsPath)) {
            Artisan::call('migrate', [
                '--path' => "plugins/{$pluginCode}/database/migrations",
                '--force' => true
            ]);
        }
    }

    /**
     * 发布插件资源
     */
    protected function publishAssets(string $pluginCode): void
    {
        $assetsPath = $this->getPluginPath($pluginCode) . '/resources/assets';
        if (File::exists($assetsPath)) {
            $publishPath = public_path('plugins/' . $pluginCode);
            File::ensureDirectoryExists($publishPath);
            File::copyDirectory($assetsPath, $publishPath);
        }
    }

    /**
     * 验证配置文件
     */
    protected function validateConfig(array $config): bool
    {
        $requiredFields = [
            'name',
            'code',
            'version',
            'description',
            'author'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                return false;
            }
        }

        // 验证插件代码格式
        if (!preg_match('/^[a-z0-9_]+$/', $config['code'])) {
            return false;
        }

        // 验证版本号格式
        if (!preg_match('/^\d+\.\d+\.\d+$/', $config['version'])) {
            return false;
        }

        return true;
    }

    /**
     * 启用插件
     */
    public function enable(string $pluginCode): bool
    {
        $plugin = $this->loadPlugin($pluginCode);

        if (!$plugin) {
            Plugin::where('code', $pluginCode)->delete();
            throw new \Exception('Plugin not found: ' . $pluginCode);
        }

        // 获取插件配置
        $dbPlugin = Plugin::query()
            ->where('code', $pluginCode)
            ->first();

        if ($dbPlugin && !empty($dbPlugin->config)) {
            $plugin->setConfig(json_decode($dbPlugin->config, true));
        }

        // 注册服务提供者
        $this->registerServiceProvider($pluginCode);

        // 加载路由
        $this->loadRoutes($pluginCode);

        // 加载视图
        $this->loadViews($pluginCode);

        // 更新数据库状态
        Plugin::query()
            ->where('code', $pluginCode)
            ->update([
                'is_enabled' => true,
                'updated_at' => now(),
            ]);
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
     * 删除插件
     *
     * @param string $pluginCode
     * @return bool
     * @throws \Exception
     */
    public function delete(string $pluginCode): bool
    {
        // 先卸载插件
        if (Plugin::where('code', $pluginCode)->exists()) {
            $this->uninstall($pluginCode);
        }

        $pluginPath = $this->getPluginPath($pluginCode);
        if (!File::exists($pluginPath)) {
            throw new \Exception('插件不存在');
        }

        // 删除插件目录
        File::deleteDirectory($pluginPath);

        return true;
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

    /**
     * 上传插件
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     * @throws \Exception
     */
    public function upload($file): bool
    {
        $tmpPath = storage_path('tmp/plugins');
        if (!File::exists($tmpPath)) {
            File::makeDirectory($tmpPath, 0755, true);
        }

        $extractPath = $tmpPath . '/' . uniqid();
        $zip = new \ZipArchive();

        if ($zip->open($file->path()) !== true) {
            throw new \Exception('无法打开插件包文件');
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $configFile = File::glob($extractPath . '/*/config.json');
        if (empty($configFile)) {
            $configFile = File::glob($extractPath . '/config.json');
        }

        if (empty($configFile)) {
            File::deleteDirectory($extractPath);
            throw new \Exception('插件包格式错误：缺少配置文件');
        }

        $pluginPath = dirname(reset($configFile));
        $config = json_decode(File::get($pluginPath . '/config.json'), true);

        if (!$this->validateConfig($config)) {
            File::deleteDirectory($extractPath);
            throw new \Exception('插件配置文件格式错误');
        }

        $targetPath = $this->pluginPath . '/' . Str::studly($config['code']);
        if (File::exists($targetPath)) {
            File::deleteDirectory($extractPath);
            throw new \Exception('插件已存在');
        }

        File::copyDirectory($pluginPath, $targetPath);
        File::deleteDirectory($pluginPath);
        File::deleteDirectory($extractPath);

        return true;
    }
}