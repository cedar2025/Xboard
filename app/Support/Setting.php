<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use App\Models\Setting as SettingModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

class Setting extends Fluent
{
    public function __construct()
    {
        $this->attributes = self::fromDatabase();
    }
    /**
     * 获取配置，并转化为数组.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return array
     */
    public function getArray($key, $default = [])
    {
        $value = $this->get($key, $default);

        if (!$value) {
            return [];
        }

        return is_array($value) ? $value : (json_decode($value, true) ?: []);
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
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * 设置配置信息.
     *
     * @param  array  $data
     * @return $this
     */
    public function set($key, $value = null)
    {
        $data = is_array($key) ? $key : [$key => $value];

        foreach ($data as $key => $value) {
            Arr::set($this->attributes, $key, $value);
        }

        return $this;
    }

    /**
     * 追加数据.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @param  mixed  $k
     * @return $this
     */
    public function add($key, $value, $k = null)
    {
        $results = $this->getArray($key);

        if ($k !== null) {
            $results[] = $value;
        } else {
            $results[$k] = $value;
        }

        return $this->set($key, $results);
    }

    /**
     * 批量追加数据.
     *
     * @param  string  $key
     * @param  array  $value
     * @return $this
     */
    public function addMany($key, array $value)
    {
        $results = $this->getArray($key);

        return $this->set($key, array_merge($results, $value));
    }

    /**
     * 保存配置到数据库.
     *
     * @param  array  $data
     * @return $this
     */
    public function save(array $data = [])
    {
        if ($data) {
            $this->set($data);
        }

        foreach ($this->attributes as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $model = SettingModel::query()
                ->where('name', $key)
                ->first() ?: new SettingModel();

            $model->fill([
                'name' => $key,
                'value' => (string) $value,
            ])->save();
        }
        Cache::forget('admin_settings');

        return $this;
    }

    /**
     * @return static
     */
    public static function fromDatabase()
    {
        $values = [];
        try {
            $values = Cache::remember('admin_settings', env('ADMIN_SETTING_CACHE', 0), function () {
                return SettingModel::pluck('value', 'name')->toArray();
            });
        } catch (QueryException $e) {
        }
        return $values;
    }
}
