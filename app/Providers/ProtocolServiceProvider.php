<?php

namespace App\Providers;

use App\Support\ProtocolManager;
use Illuminate\Support\ServiceProvider;

class ProtocolServiceProvider extends ServiceProvider
{
  /**
   * 注册服务
   *
   * @return void
   */
  public function register()
  {
    $this->app->scoped('protocols.manager', function ($app) {
      return new ProtocolManager($app);
    });

    $this->app->scoped('protocols.flags', function ($app) {
      return $app->make('protocols.manager')->getAllFlags();
    });
  }

  /**
   * 启动服务
   *
   * @return void
   */
  public function boot()
  {
    // 在启动时预加载协议类并缓存
    $this->app->make('protocols.manager')->registerAllProtocols();

  }

  /**
   * 提供的服务
   *
   * @return array
   */
  public function provides()
  {
    return [
      'protocols.manager',
      'protocols.flags',
    ];
  }
}