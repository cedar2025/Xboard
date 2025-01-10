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
        ], function ($router) {
            $router->group([
                'prefix' => 'UniProxy',
                'middleware' => 'server'
            ], function ($route) {
                $route->get('config', [UniProxyController::class, 'config']);
                $route->get('user', [UniProxyController::class, 'user']);
                $route->post('push', [UniProxyController::class, 'push']);
                $route->post('alive', [UniProxyController::class, 'alive']);
                $route->get('alivelist', [UniProxyController::class, 'alivelist']);
            });
        });
    }
}
