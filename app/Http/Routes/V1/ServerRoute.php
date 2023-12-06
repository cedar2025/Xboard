<?php
namespace App\Http\Routes\V1;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Routing\Registrar;

class ServerRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'server',
            'middleware' => 'server'
        ], function ($router) {
            $router->any('/{class}/{action}', function($class, $action) {
                $controllerClass = "\\App\\Http\\Controllers\\V1\\Server\\" . ucfirst($class) . "Controller";
                if(!(class_exists($controllerClass) && method_exists($controllerClass, $action))){
                    throw new ApiException('Not Found',404);
                };
                $ctrl = \App::make($controllerClass);
                return \App::call([$ctrl, $action]);
            });
        });
    }
}
