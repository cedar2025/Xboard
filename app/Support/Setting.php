<?php

namespace App\Support;

use App\Models\Setting as SettingModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

class Setting
{
    const CACHE_KEY = 'admin_settings';

    private $cache;
    public function __construct()
    {
        $this->cache = Cache::store('redis');
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
        return Arr::get($this->fromDatabase(), $key, $default);
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
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $key = strtolower($key);
        SettingModel::updateOrCreate(['name' => $key], ['value' => $value]);
        $this->cache->forget(self::CACHE_KEY);
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
            $this->set($key, $value);
        }

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
        return true;
    }

    /**
     * 获取配置信息.
     * @return array
     */
    public function fromDatabase(): array
    {
        try {
            return $this->cache->rememberForever(self::CACHE_KEY, function (): array {
                return array_change_key_case(SettingModel::pluck('value', 'name')->toArray(), CASE_LOWER);
            });
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
        return $this->fromDatabase();
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
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $key = strtolower($key);
        SettingModel::updateOrCreate(['name' => $key], ['value' => $value]);
        $this->cache->forget(self::CACHE_KEY);
        return true;
    }
}
