<?php

namespace App\Http\Controllers\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ThemeController extends Controller
{
    private $themes;
    private $path;

    public function __construct()
    {
        $this->path = $path = public_path('theme/');
        $this->themes = array_map(function ($item) use ($path) {
            return str_replace($path, '', $item);
        }, glob($path . '*'));
    }

    public function getThemes()
    {
        $themeConfigs = [];
        foreach ($this->themes as $theme) {
            $themeConfigFile = $this->path . "{$theme}/config.json";
            if (!File::exists($themeConfigFile)) continue;
            $themeConfig = json_decode(File::get($themeConfigFile), true);
            if (!isset($themeConfig['configs']) || !is_array($themeConfig)) continue;
            $themeConfigs[$theme] = $themeConfig;
            if (admin_setting("theme_{$theme}")) continue;
            $themeService = new ThemeService($theme);
            $themeService->init();
        }
        return response([
            'data' => [
                'themes' => $themeConfigs,
                'active' => admin_setting('frontend_theme', 'Xboard')
            ]
        ]);
    }

    public function getThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|in:' . join(',', $this->themes)
        ]);
        return response([
            'data' => admin_setting("theme_{$payload['name']}") ?? config("theme.{$payload['name']}")
        ]);
    }

    public function saveThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|in:' . join(',', $this->themes),
            'config' => 'required'
        ]);
        $payload['config'] = json_decode(base64_decode($payload['config']), true);
        if (!$payload['config'] || !is_array($payload['config'])) throw new ApiException(422, '参数有误');
        $themeConfigFile = public_path("theme/{$payload['name']}/config.json");
        if (!File::exists($themeConfigFile)) throw new ApiException(500, '主题不存在');
        $themeConfig = json_decode(File::get($themeConfigFile), true);
        if (!isset($themeConfig['configs']) || !is_array($themeConfig)) throw new ApiException(500, '主题配置文件有误');
        $validateFields = array_column($themeConfig['configs'], 'field_name');
        $config = [];
        foreach ($validateFields as $validateField) {
            $config[$validateField] = isset($payload['config'][$validateField]) ? $payload['config'][$validateField] : '';
        }

        File::ensureDirectoryExists(base_path() . '/config/theme/');
        // $data = var_export($config, 1);

        try {
            admin_setting(["theme_{$payload['name']}" => $config]);
//            sleep(2);
        } catch (\Exception $e) {
            throw new ApiException(500, '保存失败');
        }

        return response([
            'data' => $config
        ]);
    }
}
