<?php
namespace App\Http\Routes\V2;

use Illuminate\Contracts\Routing\Registrar;

class UserRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'user',
            'middleware' => 'user'
        ], function ($router) {
            // User
            $router->get('/resetSecurity', 'V1\\User\\UserController@resetSecurity');
            $router->get('/info', 'V1\\User\\UserController@info');
        });
    }
}
