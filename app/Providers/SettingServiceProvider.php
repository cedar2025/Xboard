<?php

namespace App\Providers;

use App\Support\Setting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;

class SettingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->bind(Setting::class, function (Application $app) {
            return new Setting();
        });

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
