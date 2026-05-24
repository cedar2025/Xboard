<?php
namespace App\Http\Routes\V1;

use App\Http\Controllers\V1\Guest\CommController;
use App\Http\Controllers\V1\Guest\AppUpdateController;
use App\Http\Controllers\V1\Guest\AppDownloadController;
use App\Http\Controllers\V1\Guest\PaymentController;
use App\Http\Controllers\V1\Guest\PlanController;
use App\Http\Controllers\V1\Guest\TelegramController;
use Illuminate\Contracts\Routing\Registrar;

class GuestRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'guest'
        ], function ($router) {
            // Plan
            $router->get('/plan/fetch', [PlanController::class, 'fetch']);
            // Telegram
            $router->post('/telegram/webhook', [TelegramController::class, 'webhook']);
            // Payment
            $router->match(['get', 'post'], '/payment/notify/{method}/{uuid}', [PaymentController::class, 'notify']);
            // Comm
            $router->get('/comm/config', [CommController::class, 'config']);
        });

        $router->group([
            'prefix' => 'app'
        ], function ($router) {
            $router->get('/update', [AppUpdateController::class, 'check']);
        });

        $router->get('/app-downloads', [AppDownloadController::class, 'index']);
        $router->get('/app-downloads/{artifact}/download', [AppDownloadController::class, 'download'])
            ->middleware(['signed', 'throttle:30,1'])
            ->name('app-downloads.download');
    }
}
