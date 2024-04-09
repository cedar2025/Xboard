<?php
namespace App\Http\Routes\V1;

use App\Http\Controllers\V1\Server\DeepbworkController;
use App\Http\Controllers\V1\Server\ShadowsocksTidalabController;
use App\Http\Controllers\V1\Server\TrojanTidalabController;
use App\Http\Controllers\V1\Server\UniProxyController;
use Illuminate\Contracts\Routing\Registrar;

class ServerRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'server',
            'middleware' => 'server'
        ], function ($router) {
            $router->prefix('UniProxy')->group(function ($route) {
                $route->get('config', [UniProxyController::class, 'config']);
                $route->get('user', [UniProxyController::class, 'user']);
                $route->post('push', [UniProxyController::class, 'push']);
                $route->post('alive', [UniProxyController::class, 'alive']);
            });
            $router->prefix('Deepbwork')->group(function ($route) {
                $route->get('config', [DeepbworkController::class, 'config']);
                $route->get('user', [DeepbworkController::class, 'user']);
                $route->post('submit', [DeepbworkController::class, 'submit']);
            });
            $router->prefix('ShadowsocksTidalab')->group(function ($route) {
                $route->get('user', [ShadowsocksTidalabController::class, 'user']);
                $route->post('submit', [ShadowsocksTidalabController::class, 'submit']);
            });
            $router->prefix('TrojanTidalab')->group(function ($route) {
                $route->get('config', [TrojanTidalabController::class, 'config']);
                $route->get('user', [TrojanTidalabController::class, 'user']);
                $route->post('submit', [TrojanTidalabController::class, 'submit']);
            });
        });
    }
}
