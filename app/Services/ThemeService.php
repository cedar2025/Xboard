<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Exception;
use ZipArchive;

class ThemeService
{
    private const THEME_DIR = 'theme/';
    private const CONFIG_FILE = 'config.json';
    private const SETTING_PREFIX = 'theme_';

    /**
     * 获取所有可用主题列表
     */
    public function getList(): array
    {
        $path = base_path(self::THEME_DIR);
        return collect(File::directories($path))
            ->mapWithKeys(function ($dir) {
                $name = basename($dir);
                $config = $this->readConfigFile($name);
                return $config ? [$name => $config] : [];
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
                throw new Exception('Invalid theme package');
            }

            // 验证主题包结构
            $hasConfig = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (basename($zip->getNameIndex($i)) === self::CONFIG_FILE) {
                    $hasConfig = true;
                    break;
                }
            }

            if (!$hasConfig) {
                throw new Exception('Theme configuration file not found');
            }

            // 解压并移动到主题目录
            $zip->extractTo($tmpPath);
            $zip->close();

            $themeName = basename($tmpPath);
            $targetPath = base_path(self::THEME_DIR . $themeName);

            if (File::exists($targetPath)) {
                throw new Exception('Theme already exists');
            }

            File::moveDirectory($tmpPath, $targetPath);

            // 初始化主题配置
            $this->initConfig($themeName);
            return true;

        } catch (Exception $e) {
            Log::error('Theme upload failed', ['error' => $e->getMessage()]);
            if (File::exists($tmpPath)) {
                File::deleteDirectory($tmpPath);
            }
            throw $e;
        }
    }

    /**
     * 切换主题
     */
    public static function switchTheme(string $theme): bool
    {
        return (new self())->switch($theme);
    }

    /**
     * 切换主题
     */
    public function switch(string $theme): bool
    {
        $currentTheme = admin_setting('current_theme');
        if ($theme === $currentTheme) {
            return true;
        }

        try {
            $this->validateTheme($theme);

            // 复制主题文件到public目录
            $sourcePath = base_path(self::THEME_DIR . $theme);
            $targetPath = public_path(self::THEME_DIR . $theme);

            if (!File::copyDirectory($sourcePath, $targetPath)) {
                throw new Exception('Failed to copy theme files');
            }

            // 清理旧主题文件
            if ($currentTheme) {
                $oldPath = public_path(self::THEME_DIR . $currentTheme);
                File::exists($oldPath) && File::deleteDirectory($oldPath);
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
        if ($theme === admin_setting('current_theme')) {
            throw new Exception('Cannot delete active theme');
        }

        try {
            $themePath = base_path(self::THEME_DIR . $theme);
            $publicPath = public_path(self::THEME_DIR . $theme);

            if (!File::exists($themePath)) {
                throw new Exception('Theme not found');
            }

            File::deleteDirectory($themePath);
            File::exists($publicPath) && File::deleteDirectory($publicPath);

            // 清理主题配置
            admin_setting([self::SETTING_PREFIX . $theme => null]);
            return true;

        } catch (Exception $e) {
            Log::error('Theme deletion failed', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }
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
            $this->validateTheme($theme);
            $schema = $this->readConfigFile($theme);

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
        $file = base_path(self::THEME_DIR . $theme . '/' . self::CONFIG_FILE);
        return File::exists($file) ? json_decode(File::get($file), true) : null;
    }

    /**
     * 验证主题
     */
    private function validateTheme(string $theme): void
    {
        if (!$this->readConfigFile($theme)) {
            throw new Exception("Invalid theme: {$theme}");
        }
    }

    /**
     * 初始化主题配置
     */
    private function initConfig(string $theme): void
    {
        $config = $this->readConfigFile($theme);
        if (!$config)
            return;

        $defaults = collect($config['configs'] ?? [])
            ->mapWithKeys(fn($col) => [$col['field_name'] => $col['default_value'] ?? ''])
            ->toArray();

        admin_setting([self::SETTING_PREFIX . $theme => $defaults]);
    }
}
