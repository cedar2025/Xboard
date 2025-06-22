<?php

namespace App\Services;

use App\Jobs\StatServerJob;
use App\Jobs\StatUserJob;
use App\Jobs\TrafficFetchJob;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\Plugin\HookManager;
use App\Services\TrafficResetService;
use App\Models\TrafficResetLog;

class UserService
{
    /**
     * Get the remaining days until the next traffic reset for a user.
     * This method reuses the TrafficResetService logic for consistency.
     */
    public function getResetDay(User $user): ?int
    {
        // Use TrafficResetService to calculate the next reset time
        $trafficResetService = app(TrafficResetService::class);
        $nextResetTime = $trafficResetService->calculateNextResetTime($user);
        
        if (!$nextResetTime) {
            return null;
        }
        
        // Calculate the remaining days from now to the next reset time
        $now = time();
        $resetTimestamp = $nextResetTime->timestamp;
        
        if ($resetTimestamp <= $now) {
            return 0; // Reset time has passed or is now
        }
        
        // Calculate the difference in days (rounded up)
        $daysDifference = ceil(($resetTimestamp - $now) / 86400);
        
        return (int) $daysDifference;
    }

    public function isAvailable(User $user)
    {
        if (!$user->banned && $user->transfer_enable && ($user->expired_at > time() || $user->expired_at === NULL)) {
            return true;
        }
        return false;
    }

    public function getAvailableUsers()
    {
        return User::whereRaw('u + d < transfer_enable')
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                    ->orWhere('expired_at', NULL);
            })
            ->where('banned', 0)
            ->get();
    }

    public function getUnAvailbaleUsers()
    {
        return User::where(function ($query) {
            $query->where('expired_at', '<', time())
                ->orWhere('expired_at', 0);
        })
            ->where(function ($query) {
                $query->where('plan_id', NULL)
                    ->orWhere('transfer_enable', 0);
            })
            ->get();
    }

    public function getUsersByIds($ids)
    {
        return User::whereIn('id', $ids)->get();
    }

    public function getAllUsers()
    {
        return User::all();
    }

    public function addBalance(int $userId, int $balance): bool
    {
        $user = User::lockForUpdate()->find($userId);
        if (!$user) {
            return false;
        }
        $user->balance = $user->balance + $balance;
        if ($user->balance < 0) {
            return false;
        }
        if (!$user->save()) {
            return false;
        }
        return true;
    }

    public function isNotCompleteOrderByUserId(int $userId): bool
    {
        $order = Order::whereIn('status', [0, 1])
            ->where('user_id', $userId)
            ->first();
        if (!$order) {
            return false;
        }
        return true;
    }

    public function trafficFetch(array $server, string $protocol, array $data)
    {
        list($server, $protocol, $data) = HookManager::filter('traffic.before_process', [
            $server,
            $protocol, 
            $data
        ]);

        $timestamp = strtotime(date('Y-m-d'));
        collect($data)->chunk(1000)->each(function ($chunk) use ($timestamp, $server, $protocol) {
            TrafficFetchJob::dispatch($server, $chunk->toArray(), $protocol, $timestamp);
            StatUserJob::dispatch($server, $chunk->toArray(), $protocol, 'd');
            StatServerJob::dispatch($server, $chunk->toArray(), $protocol, 'd');
        });
    }

    /**
     * 获取用户流量信息（增加重置检查）
     */
    public function getUserTrafficInfo(User $user): array
    {
        // 检查是否需要重置流量
        app(TrafficResetService::class)->checkAndReset($user, TrafficResetLog::SOURCE_USER_ACCESS);
        
        // 重新获取用户数据（可能已被重置）
        $user->refresh();
        
        return [
            'upload' => $user->u ?? 0,
            'download' => $user->d ?? 0,
            'total_used' => $user->getTotalUsedTraffic(),
            'total_available' => $user->transfer_enable ?? 0,
            'remaining' => $user->getRemainingTraffic(),
            'usage_percentage' => $user->getTrafficUsagePercentage(),
            'next_reset_at' => $user->next_reset_at,
            'last_reset_at' => $user->last_reset_at,
            'reset_count' => $user->reset_count,
        ];
    }
}
