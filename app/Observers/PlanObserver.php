<?php

namespace App\Observers;

use App\Models\Plan;
use App\Models\User;
use App\Services\TrafficResetService;

class PlanObserver
{
    /**
     * reset user  next_reset_at
     */
    public function updated(Plan $plan): void
    {
        if (!$plan->isDirty('reset_traffic_method')) {
            return;
        }
        $trafficResetService = app(TrafficResetService::class);
        User::where('plan_id', $plan->id)
            ->where('banned', 0)
            ->where(function ($query) {
                $query->where('expired_at', '>', time())
                    ->orWhereNull('expired_at');
            })
            ->lazyById(500)
            ->each(function (User $user) use ($trafficResetService) {
                $nextResetTime = $trafficResetService->calculateNextResetTime($user);
                $user->update([
                    'next_reset_at' => $nextResetTime?->timestamp,
                ]);
            });
    }
}

