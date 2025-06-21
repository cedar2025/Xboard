<?php

namespace App\Observers;

use App\Models\User;
use App\Services\TrafficResetService;
use Illuminate\Support\Facades\Log;

/**
 * 用户模型观察者
 * 主要用于监听用户到期时间变化，自动更新流量重置时间
 */
class UserObserver
{
  /**
   * 流量重置服务
   */
  private TrafficResetService $trafficResetService;

  public function __construct(TrafficResetService $trafficResetService)
  {
    $this->trafficResetService = $trafficResetService;
  }

  /**
   * 监听用户更新事件
   * 当 expired_at 或 plan_id 发生变化时，重新计算下次重置时间
   */
  public function updating(User $user): void
  {
    // 检查是否有相关字段发生变化
    $relevantFields = ['expired_at', 'plan_id'];
    $hasRelevantChanges = false;

    foreach ($relevantFields as $field) {
      if ($user->isDirty($field)) {
        $hasRelevantChanges = true;
        break;
      }
    }

    if (!$hasRelevantChanges) {
      return; // 没有相关字段变化，直接返回
    }

    try {
      if (!$user->plan_id) {
        $user->next_reset_at = null;
        return;
      }

      // 重新计算下次重置时间
      $nextResetTime = $this->trafficResetService->calculateNextResetTime($user);

      if ($nextResetTime) {
        $user->next_reset_at = $nextResetTime->timestamp;
      } else {
        // 如果计算结果为空，清除重置时间
        $user->next_reset_at = null;
      }

    } catch (\Exception $e) {
      Log::error('更新用户流量重置时间失败', [
        'user_id' => $user->id,
        'email' => $user->email,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      // 不阻止用户更新操作，只记录错误
    }
  }

  /**
   * 监听用户创建事件
   * 为新用户设置初始的重置时间
   */
  public function created(User $user): void
  {
    // 如果用户有套餐和到期时间，设置初始重置时间
    if ($user->plan_id && $user->expired_at) {
      try {
        $this->trafficResetService->setInitialResetTime($user);
      } catch (\Exception $e) {
        Log::error('设置新用户流量重置时间失败', [
          'user_id' => $user->id,
          'email' => $user->email,
          'error' => $e->getMessage(),
        ]);
      }
    }
  }
}