<?php

namespace App\Http\Routes\V2;

use Illuminate\Contracts\Routing\Registrar;

class PassportRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'passport'
        ], function ($router) {
            // Auth
            $router->post('/auth/magicLogin', 'V2\\Passport\\AuthController@magicLogin');
        });
    }
}
