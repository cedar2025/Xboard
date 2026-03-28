<?php

namespace App\Observers;

use App\Jobs\NodeUserSyncJob;
use App\Models\User;
use App\Services\TrafficResetService;

class UserObserver
{
  public function __construct(
    private readonly TrafficResetService $trafficResetService
  ) {
  }

  public function updated(User $user): void
  {
    if ($user->isDirty(['plan_id', 'expired_at'])) {
      $this->recalculateNextResetAt($user);
    }

    if ($user->isDirty(['group_id', 'uuid', 'speed_limit', 'device_limit', 'banned', 'expired_at', 'transfer_enable', 'u', 'd', 'plan_id'])) {
      $oldGroupId = $user->isDirty('group_id') ? $user->getOriginal('group_id') : null;
      NodeUserSyncJob::dispatch($user->id, 'updated', $oldGroupId);
    }
  }

  public function created(User $user): void
  {
    $this->recalculateNextResetAt($user);
    NodeUserSyncJob::dispatch($user->id, 'created');
  }

  public function deleted(User $user): void
  {
    if ($user->group_id) {
      NodeUserSyncJob::dispatch($user->id, 'deleted', $user->group_id);
    }
  }

  /**
   * 根据当前用户状态重新计算 next_reset_at
   */
  private function recalculateNextResetAt(User $user): void
  {
    $user->refresh();
    User::withoutEvents(function () use ($user) {
      $nextResetTime = $this->trafficResetService->calculateNextResetTime($user);
      $user->next_reset_at = $nextResetTime?->timestamp;
      $user->save();
    });
  }
}