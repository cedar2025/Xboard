<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //
        if (admin_setting('force_https')) {
            resolve(\Illuminate\Routing\UrlGenerator::class)->forceScheme('https');
        }

        RateLimiter::for('send-email', function () {
            return Limit::perSecond(8)->by('smtp');
        });

        RateLimiter::for('app-download-prepare', function (Request $request) {
            $artifact = $request->route('artifact') ?: 'unknown';
            if (is_object($artifact) && method_exists($artifact, 'getKey')) {
                $artifact = $artifact->getKey();
            }

            $identity = $request->user()?->id
                ? 'user:' . $request->user()?->id
                : 'ip:' . $request->ip();

            return Limit::perMinute(60)
                ->by($identity . '|artifact:' . $artifact)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => 'fail',
                        'message' => '下载请求过于频繁，请稍后重试。',
                        'data' => null,
                        'error' => null,
                    ], 429, $headers);
                });
        });

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::group([
            'prefix' => '/api/v1',
            'middleware' => 'api',
            'namespace' => $this->namespace
        ], function ($router) {
            foreach (glob(app_path('Http//Routes//V1') . '/*.php') as $file) {
                $this->app->make('App\\Http\\Routes\\V1\\' . basename($file, '.php'))->map($router);
            }
        });


        Route::group([
            'prefix' => '/api/v2',
            'middleware' => 'api',
            'namespace' => $this->namespace
        ], function ($router) {
            foreach (glob(app_path('Http//Routes//V2') . '/*.php') as $file) {
                $this->app->make('App\\Http\\Routes\\V2\\' . basename($file, '.php'))->map($router);
            }
        });
    }
}
