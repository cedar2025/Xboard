<?php

namespace App\Providers;

use App\Models\Plugin;
use App\Services\Plugin\HookManager;
use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Octane\Events\WorkerStarting;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PluginManager::class, function ($app) {
            return new PluginManager();
        });
    }

    public function boot(): void
    {
        foreach (['plugins', 'plugins-core'] as $dir) {
            $path = base_path($dir);
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
}