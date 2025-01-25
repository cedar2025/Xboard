<?php

namespace App\Http\Middleware;

use App\Models\Plugin;
use App\Services\Plugin\PluginManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InitializePlugins
{
    protected $pluginManager;

    public function __construct(PluginManager $pluginManager)
    {
        $this->pluginManager = $pluginManager;
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            $plugins = Plugin::query()
                ->where('is_enabled', true)
                ->get();

            foreach ($plugins as $plugin) {
                $this->pluginManager->enable($plugin->code);
            }
        } catch (\Exception $e) {
            Log::error('Failed to load plugins: ' . $e->getMessage());
        }

        return $next($request);
    }
} 