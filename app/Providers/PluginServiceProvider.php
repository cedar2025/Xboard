<?php

namespace App\Providers;

use App\Models\Plugin;
use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

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

        if (!file_exists(base_path('plugins'))) {
            mkdir(base_path('plugins'), 0755, true);
        }

        try {
            $plugins = Plugin::query()
                ->where('is_enabled', true)
                ->get();

            foreach ($plugins as $plugin) {
                $manager = $this->app->make(PluginManager::class);
                $manager->enable($plugin->code);

            }
        } catch (\Exception $e) {
            \Log::error('Failed to load plugins: ' . $e->getMessage());
        }
    }
}