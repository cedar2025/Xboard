<?php

namespace App\Providers;

use App\Models\Server;
use App\Models\ServerRoute;
use App\Models\Plan;
use App\Models\User;
use App\Observers\PlanObserver;
use App\Observers\ServerObserver;
use App\Observers\ServerRouteObserver;
use App\Observers\UserObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 事件监听器映射
     * @var array<string, array<int, class-string>>
     */
    protected $listen = [
    ];

    /**
     * 注册任何事件
     * @return void
     */
    public function boot()
    {
        parent::boot();

        User::observe(UserObserver::class);
        Plan::observe(PlanObserver::class);
        Server::observe(ServerObserver::class);
        ServerRoute::observe(ServerRouteObserver::class);


    }
}
