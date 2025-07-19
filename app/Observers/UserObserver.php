<?php

namespace App\Observers;

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
      $user->refresh();
      User::withoutEvents(function () use ($user) {
        $nextResetTime = $this->trafficResetService->calculateNextResetTime($user);
        $user->next_reset_at = $nextResetTime?->timestamp;
        $user->save();
      });
    }
  }
}