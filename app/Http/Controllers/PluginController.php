<?php

namespace App\Http\Controllers;

use App\Traits\HasPluginConfig;

/**
 * 插件控制器基类
 * 
 * 为所有插件控制器提供通用功能
 */
abstract class PluginController extends Controller
{
  use HasPluginConfig;

  /**
   * 执行插件操作前的检查
   */
  protected function beforePluginAction(): ?array
  {
    return null;
  }
}