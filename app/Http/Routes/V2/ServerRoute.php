<?php
namespace App\Http\Routes\V2;

use App\Http\Controllers\V2\Server\ServerController;
use Illuminate\Contracts\Routing\Registrar;


class ServerRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'server'
        ], function ($router) {
            $router->get('/config', [ServerController::class, 'config']);
        });
    }
}
