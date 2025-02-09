<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use Illuminate\Support\Facades\File;

class PluginConfigService
{
    protected $pluginManager;

    public function __construct()
    {
        $this->pluginManager = app(PluginManager::class);
    }

    /**
     * 获取插件配置
     *
     * @param string $pluginCode
     * @return array
     */
    public function getConfig(string $pluginCode): array
    {
        $defaultConfig = $this->getDefaultConfig($pluginCode);
        if (empty($defaultConfig)) {
            return [];
        }
        $dbConfig = $this->getDbConfig($pluginCode);

        $result = [];
        foreach ($defaultConfig as $key => $item) {
            $result[$key] = [
                'type' => $item['type'],
                'label' => $item['label'] ?? '',
                'placeholder' => $item['placeholder'] ?? '',
                'description' => $item['description'] ?? '',
                'value' => $dbConfig[$key] ?? $item['default']
            ];
        }

        return $result;
    }

    /**
     * 更新插件配置
     *
     * @param string $pluginCode
     * @param array $config
     * @return bool
     */
    public function updateConfig(string $pluginCode, array $config): bool
    {
        $defaultConfig = $this->getDefaultConfig($pluginCode);
        if (empty($defaultConfig)) {
            throw new \Exception('插件配置结构不存在');
        }
        $values = [];
        foreach ($config as $key => $value) {
            if (!isset($defaultConfig[$key])) {
                continue;
            }
            $values[$key] = $value;
        }
        Plugin::query()
            ->where('code', $pluginCode)
            ->update([
                'config' => json_encode($values),
                'updated_at' => now()
            ]);

        return true;
    }

    /**
     * 获取插件默认配置
     *
     * @param string $pluginCode
     * @return array
     */
    protected function getDefaultConfig(string $pluginCode): array
    {
        $configFile = $this->pluginManager->getPluginPath($pluginCode) . '/config.json';
        if (!File::exists($configFile)) {
            return [];
        }

        $config = json_decode(File::get($configFile), true);
        return $config['config'] ?? [];
    }

    /**
     * 获取数据库中的配置
     *
     * @param string $pluginCode
     * @return array
     */
    public function getDbConfig(string $pluginCode): array
    {
        $plugin = Plugin::query()
            ->where('code', $pluginCode)
            ->first();

        if (!$plugin || empty($plugin->config)) {
            return [];
        }

        return json_decode($plugin->config, true);
    }
}