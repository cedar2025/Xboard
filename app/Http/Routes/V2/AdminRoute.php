<?php
namespace App\Http\Routes\V2;

use App\Http\Controllers\V2\Admin\ConfigController;
use App\Http\Controllers\V2\Admin\PlanController;
use App\Http\Controllers\V2\Admin\Server\GroupController;
use App\Http\Controllers\V2\Admin\Server\RouteController;
use App\Http\Controllers\V2\Admin\Server\ManageController;
use App\Http\Controllers\V2\Admin\OrderController;
use App\Http\Controllers\V2\Admin\UserController;
use App\Http\Controllers\V2\Admin\StatController;
use App\Http\Controllers\V2\Admin\NoticeController;
use App\Http\Controllers\V2\Admin\TicketController;
use App\Http\Controllers\V2\Admin\CouponController;
use App\Http\Controllers\V2\Admin\KnowledgeController;
use App\Http\Controllers\V2\Admin\PaymentController;
use App\Http\Controllers\V2\Admin\SystemController;
use App\Http\Controllers\V2\Admin\ThemeController;
use Illuminate\Contracts\Routing\Registrar;

class AdminRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))),
            'middleware' => ['admin', 'log'],
        ], function ($router) {
            // Config
            $router->group([
                'prefix' => 'config'
            ], function ($router) {
                $router->get('/fetch', [ConfigController::class, 'fetch']);
                $router->post('/save', [ConfigController::class, 'save']);
                $router->get('/getEmailTemplate', [ConfigController::class, 'getEmailTemplate']);
                $router->get('/getThemeTemplate', [ConfigController::class, 'getThemeTemplate']);
                $router->post('/setTelegramWebhook', [ConfigController::class, 'setTelegramWebhook']);
                $router->post('/testSendMail', [ConfigController::class, 'testSendMail']);
            });

            // Plan
            $router->group([
                'prefix' => 'plan'
            ], function ($router) {
                $router->get('/fetch', [PlanController::class, 'fetch']);
                $router->post('/save', [PlanController::class, 'save']);
                $router->post('/drop', [PlanController::class, 'drop']);
                $router->post('/update', [PlanController::class, 'update']);
                $router->post('/sort', [PlanController::class, 'sort']);
            });

            // Server
            $router->group([
                'prefix' => 'server/group'
            ], function ($router) {
                $router->get('/fetch', [GroupController::class, 'fetch']);
                $router->post('/save', [GroupController::class, 'save']);
                $router->post('/drop', [GroupController::class, 'drop']);
            });
            $router->group([
                'prefix' => 'server/route'
            ], function ($router) {
                $router->get('/fetch', [RouteController::class, 'fetch']);
                $router->post('/save', [RouteController::class, 'save']);
                $router->post('/drop', [RouteController::class, 'drop']);
            });
            $router->group([
                'prefix' => 'server/manage'
            ], function ($router) {
                $router->get('/getNodes', [ManageController::class, 'getNodes']);
                $router->post('/sort', [ManageController::class, 'sort']);
            });

            // 节点更新接口
            $router->group([
                'prefix' => 'server/manage'
            ], function ($router) {
                $router->post('/update', [ManageController::class, 'update']);
                $router->post('/save', [ManageController::class, 'save']);
                $router->post('/drop', [ManageController::class, 'drop']);
                $router->post('/copy', [ManageController::class, 'copy']);
                $router->post('/sort', [ManageController::class, 'sort']);
            });

            // Order
            $router->group([
                'prefix' => 'order'
            ], function ($router) {
                $router->any('/fetch', [OrderController::class, 'fetch']);
                $router->post('/update', [OrderController::class, 'update']);
                $router->post('/assign', [OrderController::class, 'assign']);
                $router->post('/paid', [OrderController::class, 'paid']);
                $router->post('/cancel', [OrderController::class, 'cancel']);
                $router->post('/detail', [OrderController::class, 'detail']);
            });

            // User
            $router->group([
                'prefix' => 'user'
            ], function ($router) {
                $router->any('/fetch', [UserController::class, 'fetch']);
                $router->post('/update', [UserController::class, 'update']);
                $router->get('/getUserInfoById', [UserController::class, 'getUserInfoById']);
                $router->post('/generate', [UserController::class, 'generate']);
                $router->post('/dumpCSV', [UserController::class, 'dumpCSV']);
                $router->post('/sendMail', [UserController::class, 'sendMail']);
                $router->post('/ban', [UserController::class, 'ban']);
                $router->post('/resetSecret', [UserController::class, 'resetSecret']);
                $router->post('/setInviteUser', [UserController::class, 'setInviteUser']);
                $router->post('/destroy', [UserController::class, 'destroy']);
            });

            // Stat
            $router->group([
                'prefix' => 'stat'
            ], function ($router) {
                $router->get('/getOverride', [StatController::class, 'getOverride']);
                $router->get('/getStats', [StatController::class, 'getStats']);
                $router->get('/getServerLastRank', [StatController::class, 'getServerLastRank']);
                $router->get('/getServerYesterdayRank', [StatController::class, 'getServerYesterdayRank']);
                $router->get('/getOrder', [StatController::class, 'getOrder']);
                $router->any('/getStatUser', [StatController::class, 'getStatUser']);
                $router->get('/getRanking', [StatController::class, 'getRanking']);
                $router->get('/getStatRecord', [StatController::class, 'getStatRecord']);
                $router->get('/getTrafficRank', [StatController::class, 'getTrafficRank']);
            });

            // Notice
            $router->group([
                'prefix' => 'notice'
            ], function ($router) {
                $router->get('/fetch', [NoticeController::class, 'fetch']);
                $router->post('/save', [NoticeController::class, 'save']);
                $router->post('/update', [NoticeController::class, 'update']);
                $router->post('/drop', [NoticeController::class, 'drop']);
                $router->post('/show', [NoticeController::class, 'show']);
                $router->post('/sort', [NoticeController::class, 'sort']);
            });

            // Ticket
            $router->group([
                'prefix' => 'ticket'
            ], function ($router) {
                $router->any('/fetch', [TicketController::class, 'fetch']);
                $router->post('/reply', [TicketController::class, 'reply']);
                $router->post('/close', [TicketController::class, 'close']);
            });

            // Coupon
            $router->group([
                'prefix' => 'coupon'
            ], function ($router) {
                $router->any('/fetch', [CouponController::class, 'fetch']);
                $router->post('/generate', [CouponController::class, 'generate']);
                $router->post('/drop', [CouponController::class, 'drop']);
                $router->post('/show', [CouponController::class, 'show']);
                $router->post('/update', [CouponController::class, 'update']);
            });

            // Knowledge
            $router->group([
                'prefix' => 'knowledge'
            ], function ($router) {
                $router->get('/fetch', [KnowledgeController::class, 'fetch']);
                $router->get('/getCategory', [KnowledgeController::class, 'getCategory']);
                $router->post('/save', [KnowledgeController::class, 'save']);
                $router->post('/show', [KnowledgeController::class, 'show']);
                $router->post('/drop', [KnowledgeController::class, 'drop']);
                $router->post('/sort', [KnowledgeController::class, 'sort']);
            });

            // Payment  
            $router->group([
                'prefix' => 'payment'
            ], function ($router) {
                $router->get('/fetch', [PaymentController::class, 'fetch']);
                $router->get('/getPaymentMethods', [PaymentController::class, 'getPaymentMethods']);
                $router->post('/getPaymentForm', [PaymentController::class, 'getPaymentForm']);
                $router->post('/save', [PaymentController::class, 'save']);
                $router->post('/drop', [PaymentController::class, 'drop']);
                $router->post('/show', [PaymentController::class, 'show']);
                $router->post('/sort', [PaymentController::class, 'sort']);
            });

            // System
            $router->group([
                'prefix' => 'system'
            ], function ($router) {
                $router->get('/getSystemStatus', [SystemController::class, 'getSystemStatus']);
                $router->get('/getQueueStats', [SystemController::class, 'getQueueStats']);
                $router->get('/getQueueWorkload', [SystemController::class, 'getQueueWorkload']);
                $router->get('/getQueueMasters', '\\Laravel\\Horizon\\Http\\Controllers\\MasterSupervisorController@index');
                $router->get('/getSystemLog', [SystemController::class, 'getSystemLog']);
                $router->get('/getHorizonFailedJobs', [SystemController::class, 'getHorizonFailedJobs']);
                $router->post('/clearSystemLog', [SystemController::class, 'clearSystemLog']);
                $router->get('/getLogClearStats', [SystemController::class, 'getLogClearStats']);
            });

            // Update
            // $router->group([
            //     'prefix' => 'update'
            // ], function ($router) {
            //     $router->get('/check', [UpdateController::class, 'checkUpdate']);
            //     $router->post('/execute', [UpdateController::class, 'executeUpdate']);
            // });

            // Theme
            $router->group([
                'prefix' => 'theme'
            ], function ($router) {
                $router->get('/getThemes', [ThemeController::class, 'getThemes']);
                $router->post('/upload', [ThemeController::class, 'upload']);
                $router->post('/delete', [ThemeController::class, 'delete']);
                $router->post('/saveThemeConfig', [ThemeController::class, 'saveThemeConfig']);
                $router->post('/getThemeConfig', [ThemeController::class, 'getThemeConfig']);
            });

            // Plugin
            $router->group([
                'prefix' => 'plugin'
            ], function ($router) {
                $router->get('/getPlugins', [\App\Http\Controllers\V2\Admin\PluginController::class, 'index']);
                $router->post('/upload', [\App\Http\Controllers\V2\Admin\PluginController::class, 'upload']);
                $router->post('/delete', [\App\Http\Controllers\V2\Admin\PluginController::class, 'delete']);
                $router->post('install', [\App\Http\Controllers\V2\Admin\PluginController::class, 'install']);
                $router->post('uninstall', [\App\Http\Controllers\V2\Admin\PluginController::class, 'uninstall']);
                $router->post('enable', [\App\Http\Controllers\V2\Admin\PluginController::class, 'enable']);
                $router->post('disable', [\App\Http\Controllers\V2\Admin\PluginController::class, 'disable']);
                $router->get('config', [\App\Http\Controllers\V2\Admin\PluginController::class, 'getConfig']);
                $router->post('config', [\App\Http\Controllers\V2\Admin\PluginController::class, 'updateConfig']);
            });
        });

    }
}
