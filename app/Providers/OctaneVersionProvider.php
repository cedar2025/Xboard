<?php

namespace App\Providers;

use App\Services\UpdateService;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\WorkerStarting;

class OctaneVersionProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->bound('octane')) {
            $this->app['events']->listen(WorkerStarting::class, function () {
                app(UpdateService::class)->updateVersionCache();
            });
        }
    }
} 