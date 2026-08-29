<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrafficLogResource;
use App\Models\Plan;
use App\Models\StatUser;
use App\Models\TrafficResetLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatController extends Controller
{
    public function getTrafficLog(Request $request)
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('plan');
        $cycleStart = $this->getCurrentTrafficCycleStart($user)->startOfDay()->timestamp;
        $nextResetAt = (int) ($user->getRawOriginal('next_reset_at') ?? 0);

        $records = StatUser::query()
            ->where('user_id', $user->id)
            ->where('record_type', 'd')
            ->where('record_at', '>=', $cycleStart)
            ->when($nextResetAt > 0, fn ($query) => $query->where('record_at', '<', $nextResetAt))
            ->where('record_at', '<=', now()->endOfDay()->timestamp)
            ->orderBy('record_at', 'DESC')
            ->get();

        return $this->success(TrafficLogResource::collection($records));
    }

    private function getCurrentTrafficCycleStart(User $user): Carbon
    {
        $timezone = config('app.timezone');
        $createdAt = Carbon::createFromTimestamp(
            (int) ($user->getRawOriginal('created_at') ?? time()),
            $timezone
        );
        $lastResetAt = (int) ($user->getRawOriginal('last_reset_at') ?? 0);

        if ($lastResetAt > 0) {
            return $this->latestOf(
                Carbon::createFromTimestamp($lastResetAt, $timezone),
                $createdAt
            );
        }

        /** @var TrafficResetLog|null $lastResetLog */
        $lastResetLog = $user->trafficResetLogs()
            ->orderByDesc('reset_time')
            ->first();
        if ($lastResetLog?->reset_time) {
            return $this->latestOf(
                $lastResetLog->reset_time->copy()->timezone($timezone),
                $createdAt
            );
        }

        $nextResetAt = (int) ($user->getRawOriginal('next_reset_at') ?? 0);
        if ($nextResetAt <= 0) {
            return $createdAt;
        }

        $resetMethod = $user->plan?->reset_traffic_method;
        if ($resetMethod === Plan::RESET_TRAFFIC_FOLLOW_SYSTEM) {
            $resetMethod = (int) admin_setting(
                'reset_traffic_method',
                Plan::RESET_TRAFFIC_MONTHLY
            );
        }

        $nextReset = Carbon::createFromTimestamp($nextResetAt, $timezone);
        $inferredStart = match ($resetMethod) {
            Plan::RESET_TRAFFIC_FIRST_DAY_YEAR,
            Plan::RESET_TRAFFIC_YEARLY => $nextReset->copy()->subYearNoOverflow(),
            Plan::RESET_TRAFFIC_FIRST_DAY_MONTH,
            Plan::RESET_TRAFFIC_MONTHLY => $nextReset->copy()->subMonthNoOverflow(),
            default => null,
        };

        return $inferredStart
            ? $this->latestOf($inferredStart, $createdAt)
            : $createdAt;
    }

    private function latestOf(Carbon $first, Carbon $second): Carbon
    {
        return $first->greaterThan($second) ? $first : $second;
    }
}
