<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Facades\Octane;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class OctaneSchedulerProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }
        // 每半钟执行一次调度检查
        Octane::tick('scheduler', function () {
            $lock = Cache::lock('scheduler-lock', 30);

            if ($lock->get()) {
                try {
                    Artisan::call('schedule:run');
                } finally {
                    $lock->release();
                }
            }
        })->seconds(30);
    }
}