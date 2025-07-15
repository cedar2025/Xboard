<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Http\UploadedFile;
use Exception;
use ZipArchive;

class ThemeService
{
    private const SYSTEM_THEME_DIR = 'theme/';
    private const USER_THEME_DIR = '/storage/theme/';
    private const CONFIG_FILE = 'config.json';
    private const SETTING_PREFIX = 'theme_';
    private const SYSTEM_THEMES = ['Xboard', 'v2board'];

    public function __construct()
    {
        $this->registerThemeViewPaths();
    }

    /**
     * 注册主题视图路径
     */
    private function registerThemeViewPaths(): void
    {
        // 系统主题路径
        $systemPath = base_path(self::SYSTEM_THEME_DIR);
        if (File::exists($systemPath)) {
            View::addNamespace('theme', $systemPath);
        }

        // 用户主题路径
        $userPath = base_path(self::USER_THEME_DIR);
        if (File::exists($userPath)) {
            View::prependNamespace('theme', $userPath);
        }
    }

    /**
     * 获取主题视图路径
     */
    public function getThemeViewPath(string $theme): ?string
    {
        $themePath = $this->getThemePath($theme);
        if (!$themePath) {
            return null;
        }
        return $themePath . '/dashboard.blade.php';
    }

    /**
     * 获取所有可用主题列表
     */
    public function getList(): array
    {
        $themes = [];

        // 获取系统主题
        $systemPath = base_path(self::SYSTEM_THEME_DIR);
        if (File::exists($systemPath)) {
            $themes = $this->getThemesFromPath($systemPath, false);
        }

        // 获取用户主题
        $userPath = base_path(self::USER_THEME_DIR);
        if (File::exists($userPath)) {
            $themes = array_merge($themes, $this->getThemesFromPath($userPath, true));
        }

        return $themes;
    }

    /**
     * 从指定路径获取主题列表
     */
    private function getThemesFromPath(string $path, bool $canDelete): array
    {
        return collect(File::directories($path))
            ->mapWithKeys(function ($dir) use ($canDelete) {
                $name = basename($dir);
                // 检查必要文件是否存在
                if (
                    !File::exists($dir . '/' . self::CONFIG_FILE) ||
                    !File::exists($dir . '/dashboard.blade.php')
                ) {
                    return [];
                }
                $config = $this->readConfigFile($name);
                if (!$config) {
                    return [];
                }

                $config['can_delete'] = $canDelete && $name !== admin_setting('current_theme');
                $config['is_system'] = !$canDelete;
                return [$name => $config];
            })->toArray();
    }

    /**
     * 上传新主题
     */
    public function upload(UploadedFile $file): bool
    {
        $zip = new ZipArchive;
        $tmpPath = storage_path('tmp/' . uniqid());

        try {
            if ($zip->open($file->path()) !== true) {
                throw new Exception('无效的主题包');
            }

            // 查找配置文件
            $configEntry = collect(range(0, $zip->numFiles - 1))
                ->map(fn($i) => $zip->getNameIndex($i))
                ->first(fn($name) => basename($name) === self::CONFIG_FILE);

            if (!$configEntry) {
                throw new Exception('主题配置文件不存在');
            }

            // 解压并读取配置
            $zip->extractTo($tmpPath);
            $zip->close();

            $sourcePath = $tmpPath . '/' . rtrim(dirname($configEntry), '.');
            $configFile = $sourcePath . '/' . self::CONFIG_FILE;

            if (!File::exists($configFile)) {
                throw new Exception('主题配置文件不存在');
            }

            $config = json_decode(File::get($configFile), true);
            if (empty($config['name'])) {
                throw new Exception('主题名称未配置');
            }

            // 检查是否为系统主题
            if (in_array($config['name'], self::SYSTEM_THEMES)) {
                throw new Exception('不能上传与系统主题同名的主题');
            }

            // 检查必要文件
            if (!File::exists($sourcePath . '/dashboard.blade.php')) {
                throw new Exception('缺少必要的主题文件：dashboard.blade.php');
            }

            // 确保目标目录存在
            $userThemePath = base_path(self::USER_THEME_DIR);
            if (!File::exists($userThemePath)) {
                File::makeDirectory($userThemePath, 0755, true);
            }

            $targetPath = $userThemePath . $config['name'];
            if (File::exists($targetPath)) {
                $oldConfigFile = $targetPath . '/config.json';
                if (!File::exists($oldConfigFile)) {
                    throw new Exception('已存在主题缺少配置文件');
                }
                $oldConfig = json_decode(File::get($oldConfigFile), true);
                $oldVersion = $oldConfig['version'] ?? '0.0.0';
                $newVersion = $config['version'] ?? '0.0.0';
                if (version_compare($newVersion, $oldVersion, '>')) {
                    File::deleteDirectory($targetPath);
                    File::copyDirectory($sourcePath, $targetPath);
                    $this->initConfig($config['name']);
                    return true;
                } else {
                    throw new Exception('主题已存在且不是新版本');
                }
            }

            File::copyDirectory($sourcePath, $targetPath);
            $this->initConfig($config['name']);

            return true;

        } catch (Exception $e) {
            throw $e;
        } finally {
            // 清理临时文件
            if (File::exists($tmpPath)) {
                File::deleteDirectory($tmpPath);
            }
        }
    }

    /**
     * 切换主题
     */
    public function switch(string|null $theme): bool
    {
        if ($theme === null) {
            return true;
        }

        $currentTheme = admin_setting('current_theme');

        try {
            // 验证主题是否存在
            $themePath = $this->getThemePath($theme);
            if (!$themePath) {
                throw new Exception('主题不存在');
            }

            // 验证视图文件是否存在
            if (!File::exists($this->getThemeViewPath($theme))) {
                throw new Exception('主题视图文件不存在');
            }

            // 清理旧主题文件
            if ($currentTheme) {
                $oldPath = public_path('theme/' . $currentTheme);
                if (File::exists($oldPath)) {
                    File::deleteDirectory($oldPath);
                }
            }

            // 复制主题文件到public目录
            $targetPath = public_path('theme/' . $theme);
            if (!File::copyDirectory($themePath, $targetPath)) {
                throw new Exception('复制主题文件失败');
            }

            admin_setting(['current_theme' => $theme]);
            return true;

        } catch (Exception $e) {
            Log::error('Theme switch failed', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 删除主题
     */
    public function delete(string $theme): bool
    {
        try {
            // 检查是否为系统主题
            if (in_array($theme, self::SYSTEM_THEMES)) {
                throw new Exception('系统主题不能删除');
            }

            // 检查是否为当前使用的主题
            if ($theme === admin_setting('current_theme')) {
                throw new Exception('当前使用的主题不能删除');
            }

            // 获取主题路径
            $themePath = base_path(self::USER_THEME_DIR . $theme);
            if (!File::exists($themePath)) {
                throw new Exception('主题不存在');
            }

            // 删除主题文件
            File::deleteDirectory($themePath);

            // 删除public目录下的主题文件
            $publicPath = public_path('theme/' . $theme);
            if (File::exists($publicPath)) {
                File::deleteDirectory($publicPath);
            }

            // 清理主题配置
            admin_setting([self::SETTING_PREFIX . $theme => null]);
            return true;

        } catch (Exception $e) {
            Log::error('Theme deletion failed', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 检查主题是否存在
     */
    public function exists(string $theme): bool
    {
        return $this->getThemePath($theme) !== null;
    }

    /**
     * 获取主题路径
     */
    public function getThemePath(string $theme): ?string
    {
        $systemPath = base_path(self::SYSTEM_THEME_DIR . $theme);
        if (File::exists($systemPath)) {
            return $systemPath;
        }

        $userPath = base_path(self::USER_THEME_DIR . $theme);
        if (File::exists($userPath)) {
            return $userPath;
        }

        return null;
    }

    /**
     * 获取主题配置
     */
    public function getConfig(string $theme): ?array
    {
        $config = admin_setting(self::SETTING_PREFIX . $theme);
        if ($config === null) {
            $this->initConfig($theme);
            $config = admin_setting(self::SETTING_PREFIX . $theme);
        }
        return $config;
    }

    /**
     * 更新主题配置
     */
    public function updateConfig(string $theme, array $config): bool
    {
        try {
            // 验证主题是否存在
            if (!$this->getThemePath($theme)) {
                throw new Exception('主题不存在');
            }

            $schema = $this->readConfigFile($theme);
            if (!$schema) {
                throw new Exception('主题配置文件无效');
            }

            // 只保留有效的配置字段
            $validFields = collect($schema['configs'] ?? [])->pluck('field_name')->toArray();
            $validConfig = collect($config)
                ->only($validFields)
                ->toArray();

            $currentConfig = $this->getConfig($theme) ?? [];
            $newConfig = array_merge($currentConfig, $validConfig);

            admin_setting([self::SETTING_PREFIX . $theme => $newConfig]);
            return true;

        } catch (Exception $e) {
            Log::error('Config update failed', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 读取主题配置文件
     */
    private function readConfigFile(string $theme): ?array
    {
        $themePath = $this->getThemePath($theme);
        if (!$themePath) {
            return null;
        }

        $file = $themePath . '/' . self::CONFIG_FILE;
        return File::exists($file) ? json_decode(File::get($file), true) : null;
    }

    /**
     * 初始化主题配置
     */
    private function initConfig(string $theme): void
    {
        $config = $this->readConfigFile($theme);
        if (!$config) {
            return;
        }

        $defaults = collect($config['configs'] ?? [])
            ->mapWithKeys(fn($col) => [$col['field_name'] => $col['default_value'] ?? ''])
            ->toArray();
        admin_setting([self::SETTING_PREFIX . $theme => $defaults]);
    }
}
