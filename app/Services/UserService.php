<?php

namespace App\Services;

use App\Jobs\StatServerJob;
use App\Jobs\StatUserJob;
use App\Jobs\TrafficFetchJob;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\Plugin\HookManager;

class UserService
{
    private function calcResetDayByMonthFirstDay(): int
    {
        $today = (int) date('d');
        $lastDay = (int) date('d', strtotime('last day of +0 months'));
        return $lastDay - $today;
    }

    private function calcResetDayByExpireDay(int $expiredAt)
    {
        $day = (int) date('d', $expiredAt);
        $today = (int) date('d');
        $lastDay = (int) date('d', strtotime('last day of +0 months'));
        if ($day >= $today && $day >= $lastDay) {
            return $lastDay - $today;
        }
        if ($day >= $today) {
            return $day - $today;
        }

        return $lastDay - $today + $day;
    }

    private function calcResetDayByYearFirstDay(): int
    {
        $nextYear = strtotime(date("Y-01-01", strtotime('+1 year')));
        return (int) (($nextYear - time()) / 86400);
    }

    private function calcResetDayByYearExpiredAt(int $expiredAt): int
    {
        $md = date('m-d', $expiredAt);
        $nowYear = strtotime(date("Y-{$md}"));
        $nextYear = strtotime('+1 year', $nowYear);
        if ($nowYear > time()) {
            return (int) (($nowYear - time()) / 86400);
        }
        return (int) (($nextYear - time()) / 86400);
    }

    public function getResetDay(User $user): ?int
    {
        // 前置条件检查
        if ($user->expired_at <= time() || $user->expired_at === null) {
            return null;
        }

        // 获取重置方式逻辑统一
        $resetMethod = $user->plan->reset_traffic_method === Plan::RESET_TRAFFIC_FOLLOW_SYSTEM
            ? (int)admin_setting('reset_traffic_method', 0)
            : $user->plan->reset_traffic_method;

        // 验证重置方式有效性
        if (!in_array($resetMethod, array_keys(Plan::getResetTrafficMethods()), true)) {
            return null;
        }

        // 方法映射表
        $methodHandlers = [
            Plan::RESET_TRAFFIC_FIRST_DAY_MONTH => fn() => $this->calcResetDayByMonthFirstDay(),
            Plan::RESET_TRAFFIC_MONTHLY => fn() => $this->calcResetDayByExpireDay($user->expired_at),
            Plan::RESET_TRAFFIC_FIRST_DAY_YEAR => fn() => $this->calcResetDayByYearFirstDay(),
            Plan::RESET_TRAFFIC_YEARLY => fn() => $this->calcResetDayByYearExpiredAt($user->expired_at),
        ];

        $handler = $methodHandlers[$resetMethod] ?? null;
        
        return $handler ? $handler() : null;
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
}
