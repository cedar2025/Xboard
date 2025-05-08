<?php
namespace App\Http\Routes\V1;

use App\Http\Controllers\V1\Client\AppController;
use App\Http\Controllers\V1\Client\ClientController;
use Illuminate\Contracts\Routing\Registrar;

class ClientRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'client',
            'middleware' => 'client'
        ], function ($router) {
            // Client
            $router->get('/subscribe', [ClientController::class, 'subscribe'])->name('client.subscribe.legacy');
            // App
            $router->get('/app/getConfig', [AppController::class, 'getConfig']);
            $router->get('/app/getVersion', [AppController::class, 'getVersion']);
        });
    }
}
