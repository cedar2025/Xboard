<?php

namespace App\Support;

use App\Models\Setting as SettingModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class Setting
{
    const CACHE_KEY = 'admin_settings';

    private $cache;
    private static $instance = null;
    private static $inMemoryCache = null;
    private static $cacheLoaded = false;

    private function __construct()
    {
        $this->cache = Cache::store('redis');
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 获取配置.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $key = strtolower($key);
        return Arr::get($this->getInMemoryCache(), $key, $default);
    }

    /**
     * 获取内存缓存数据
     */
    private function getInMemoryCache(): array
    {
        if (!self::$cacheLoaded) {
            self::$inMemoryCache = $this->fromDatabase();
            self::$cacheLoaded = true;
        }
        return self::$inMemoryCache ?? [];
    }

    /**
     * 清除内存缓存
     */
    public static function clearInMemoryCache(): void
    {
        self::$inMemoryCache = null;
        self::$cacheLoaded = false;
    }

    /**
     * 设置配置信息.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool 设置是否成功
     */
    public function set(string $key, $value = null): bool
    {
        $key = strtolower($key);
        SettingModel::createOrUpdate($key, $value);
        $this->cache->forget(self::CACHE_KEY);
        
        // 清除内存缓存，下次访问时重新加载
        self::clearInMemoryCache();
        
        return true;
    }

    /**
     * 保存配置到数据库.
     *
     * @param  array  $settings 要保存的设置数组
     * @return bool 保存是否成功
     */
    public function save(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            $key = strtolower($key);
            SettingModel::createOrUpdate($key, $value);
        }
        
        // 批量更新后清除缓存
        $this->cache->forget(self::CACHE_KEY);
        self::clearInMemoryCache();

        return true;
    }

    /**
     * 删除配置信息
     * 
     * @param string $key
     * @return bool
     */
    public function remove($key): bool
    {
        SettingModel::where('name', $key)->delete();
        $this->cache->forget(self::CACHE_KEY);
        self::clearInMemoryCache();
        return true;
    }

    /**
     * 获取配置信息.
     * @return array
     */
    public function fromDatabase(): array
    {
        try {
            // 统一从 value 字段获取所有配置
            $settings = $this->cache->rememberForever(self::CACHE_KEY, function (): array {
                return array_change_key_case(
                    SettingModel::pluck('value', 'name')->toArray(), 
                    CASE_LOWER
                );
            });

            // 处理JSON格式的值
            foreach ($settings as $key => $value) {
                if (is_string($value) && $value !== null) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $settings[$key] = $decoded;
                    }
                }
            }
            
            return $settings;
        } catch (\Throwable $th) {
            return [];
        }
    }

    /**
     * 将所有设置转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->getInMemoryCache();
    }

    /**
     * 更新单个设置项
     * 
     * @param string $key 设置键名
     * @param mixed $value 设置值
     * @return bool 更新是否成功
     */
    public function update(string $key, $value): bool
    {
        return $this->set($key, $value);
    }

    /**
     * 批量获取配置项，优化多个配置项获取的性能
     *
     * @param array $keys 配置键名数组，格式：['key1', 'key2' => 'default_value', ...]
     * @return array 返回键值对数组
     */
    public function getBatch(array $keys): array
    {
        $cache = $this->getInMemoryCache();
        $result = [];
        
        foreach ($keys as $index => $item) {
            if (is_numeric(value: $index)) {
                // 格式：['key1', 'key2']
                $key = strtolower($item);
                $default = config('v2board.'. $item);
                $result[$item] = Arr::get($cache, $key, $default);
            } else {
                // 格式：['key1' => 'default_value']
                $key = strtolower($index);
                $default = config('v2board.'. $index) ?? $item;
                $result[$index] = Arr::get($cache, $key, $default);
            }
        }
        
        return $result;
    }
}
