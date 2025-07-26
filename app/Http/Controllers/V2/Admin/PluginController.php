<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\Plugin\PluginManager;
use App\Services\Plugin\PluginConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
     * 获取所有插件类型
     */
    public function types()
    {
        return response()->json([
            'data' => [
                [
                    'value' => Plugin::TYPE_FEATURE,
                    'label' => '功能',
                    'description' => '提供功能扩展的插件，如Telegram登录、邮件通知等',
                    'icon' => '🔧'
                ],
                [
                    'value' => Plugin::TYPE_PAYMENT,
                    'label' => '支付方式', 
                    'description' => '提供支付接口的插件，如支付宝、微信支付等',
                    'icon' => '💳'
                ]
            ]
        ]);
    }

    /**
     * 获取插件列表
     */
    public function index(Request $request)
    {
        $type = $request->query('type');
        
        $installedPlugins = Plugin::when($type, function($query) use ($type) {
                return $query->byType($type);
            })
            ->get()
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
                    $code = $config['code'];
                    $pluginType = $config['type'] ?? Plugin::TYPE_FEATURE;
                    
                    // 如果指定了类型，过滤插件
                    if ($type && $pluginType !== $type) {
                        continue;
                    }
                    
                    $installed = isset($installedPlugins[$code]);
                    $pluginConfig = $installed ? $this->configService->getConfig($code) : ($config['config'] ?? []);
                    $readmeFile = collect(['README.md', 'readme.md'])
                        ->map(fn($f) => $directory . '/' . $f)
                        ->first(fn($path) => File::exists($path));
                    $readmeContent = $readmeFile ? File::get($readmeFile) : '';

                    $plugins[] = [
                        'code' => $config['code'],
                        'name' => $config['name'],
                        'version' => $config['version'],
                        'description' => $config['description'],
                        'author' => $config['author'],
                        'type' => $pluginType,
                        'is_installed' => $installed,
                        'is_enabled' => $installed ? $installedPlugins[$code]['is_enabled'] : false,
                        'is_protected' => in_array($code, Plugin::PROTECTED_PLUGINS),
                        'can_be_deleted' => !in_array($code, Plugin::PROTECTED_PLUGINS),
                        'config' => $pluginConfig,
                        'readme' => $readmeContent,
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

        $this->pluginManager->disable($request->input('code'));
        return response()->json([
            'message' => '插件禁用成功'
        ]);

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

    /**
     * 上传插件
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:zip',
                'max:10240', // 最大10MB
            ]
        ], [
            'file.required' => '请选择插件包文件',
            'file.file' => '无效的文件类型',
            'file.mimes' => '插件包必须是zip格式',
            'file.max' => '插件包大小不能超过10MB'
        ]);

        try {
            $this->pluginManager->upload($request->file('file'));
            return response()->json([
                'message' => '插件上传成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '插件上传失败：' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 删除插件
     */
    public function delete(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $code = $request->input('code');

        // 检查是否为受保护的插件
        if (in_array($code, Plugin::PROTECTED_PLUGINS)) {
            return response()->json([
                'message' => '该插件为系统默认插件，不允许删除'
            ], 403);
        }

        try {
            $this->pluginManager->delete($code);
            return response()->json([
                'message' => '插件删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '插件删除失败：' . $e->getMessage()
            ], 400);
        }
    }
}