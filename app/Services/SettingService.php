<?php

namespace App\Services;

use App\Models\Setting as SettingModel;

class SettingService
{
    public function get($name, $default = null)
    {
        $setting = SettingModel::where('name', $name)->first();
        return $setting ? $setting->value : $default;
    }

    public function getAll(){
        return SettingModel::all()->pluck('value', 'name')->toArray();
    }
}
