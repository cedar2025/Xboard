<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\Plugin\PluginManager;
use App\Services\Plugin\PluginConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class PluginController extends Controller
{
    protected PluginManager $pluginManager;
    protected PluginConfigService $configService;

    public function __construct(
        PluginManager $pluginManager,
        PluginConfigService $configService
    ) {
        $this->pluginManager = $pluginManager;
        $this->configService = $configService;
    }

    /**
     * 获取插件列表
     */
    public function index()
    {
        $installedPlugins = Plugin::get()
            ->keyBy('code')
            ->toArray();
        $pluginPath = base_path('plugins');
        $plugins = [];

        if (File::exists($pluginPath)) {
            $directories = File::directories($pluginPath);
            foreach ($directories as $directory) {
                $pluginName = basename($directory);
                $configFile = $directory . '/config.json';
                if (File::exists($configFile)) {
                    $config = json_decode(File::get($configFile), true);
                    $installed = isset($installedPlugins[$pluginName]);
                    // 使用配置服务获取配置
                    $pluginConfig = $installed ? $this->configService->getConfig($pluginName) : ($config['config'] ?? []);
                    $plugins[] = [
                        'code' => $config['code'],
                        'name' => $config['name'],
                        'version' => $config['version'],
                        'description' => $config['description'],
                        'author' => $config['author'],
                        'is_installed' => $installed,
                        'is_enabled' => $installed ? $installedPlugins[$pluginName]['is_enabled'] : false,
                        'config' => $pluginConfig,
                    ];
                }
            }
        }

        return response()->json([
            'data' => $plugins
        ]);
    }

    /**
     * 安装插件
     */
    public function install(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        try {
            $this->pluginManager->install($request->input('code'));
            return response()->json([
                'message' => '插件安装成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '插件安装失败：' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 卸载插件
     */
    public function uninstall(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        try {
            $this->pluginManager->uninstall($request->input('code'));
            return response()->json([
                'message' => '插件卸载成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '插件卸载失败：' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 启用插件
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        try {
            $this->pluginManager->enable($request->input('code'));
            return response()->json([
                'message' => '插件启用成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '插件启用失败：' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 禁用插件
     */
    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        try {
            $this->pluginManager->disable($request->input('code'));
            return response()->json([
                'message' => '插件禁用成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '插件禁用失败：' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取插件配置
     */
    public function getConfig(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        try {
            $config = $this->configService->getConfig($request->input('code'));
            return response()->json([
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '获取配置失败：' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 更新插件配置
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'config' => 'required|array'
        ]);

        try {
            $this->configService->updateConfig(
                $request->input('code'),
                $request->input('config')
            );

            return response()->json([
                'message' => '配置更新成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '配置更新失败：' . $e->getMessage()
            ], 400);
        }
    }
}