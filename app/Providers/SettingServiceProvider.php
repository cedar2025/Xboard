<?php

namespace App\Providers;

use App\Support\Setting;
use Illuminate\Support\ServiceProvider;

class SettingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('setting', function ($app) {
            return Setting::fromDatabase(); // 假设 AdminSetting 是您的设置类
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
