<?php

namespace App\Services;

use App\Jobs\StatServerJob;
use App\Jobs\StatUserJob;
use App\Jobs\TrafficFetchJob;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Server;
use App\Models\User;
use App\Models\UserTrafficPackage;
use App\Services\Plugin\HookManager;
use App\Services\TrafficResetService;
use App\Models\TrafficResetLog;
use App\Utils\Helper;
use Illuminate\Support\Facades\Hash;

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
        if (!$user->banned && $user->id && app(TrafficPackageService::class)->hasActivePackageBalance($user->id)) {
            return true;
        }

        if (
            !$user->banned
            && $user->plan_id
            && $user->transfer_enable
            && ($user->expired_at > time() || $user->expired_at === NULL)
        ) {
            return true;
        }
        return false;
    }

    public function getAvailableUsers()
    {
        return User::where('banned', 0)
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('plan_id')
                        ->where('transfer_enable', '>', 0)
                        ->whereRaw('u + d < transfer_enable')
                        ->where(function ($query) {
                            $query->where('expired_at', '>=', time())
                                ->orWhere('expired_at', NULL);
                        });
                })->orWhereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('v2_user_traffic_packages')
                        ->whereColumn('v2_user_traffic_packages.user_id', 'v2_user.id')
                        ->where('v2_user_traffic_packages.status', UserTrafficPackage::STATUS_ACTIVE)
                        ->where('v2_user_traffic_packages.remaining_bytes', '>', 0);
                });
            })
            ->get();
    }

    public function getUnAvailbaleUsers()
    {
        return User::where(function ($query) {
            $query->where(function ($query) {
                $query->where('expired_at', '<', time())
                    ->orWhere('expired_at', 0);
            })
                ->where(function ($query) {
                    $query->where('plan_id', NULL)
                        ->orWhere('transfer_enable', 0);
                });
        })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('v2_user_traffic_packages')
                    ->whereColumn('v2_user_traffic_packages.user_id', 'v2_user.id')
                    ->where('v2_user_traffic_packages.status', UserTrafficPackage::STATUS_ACTIVE)
                    ->where('v2_user_traffic_packages.remaining_bytes', '>', 0);
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

    public function trafficFetch(Server $server, string $protocol, array $data)
    {
        $server->rate = $server->getCurrentRate();
        $server = $server->toArray();

        list($server, $protocol, $data) = HookManager::filter('traffic.process.before', [$server, $protocol, $data]);
        // Compatible with legacy hook
        list($server, $protocol, $data) = HookManager::filter('traffic.before_process', [$server, $protocol, $data]);

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
        $trafficSummary = $this->getTrafficSummary($user);

        return [
            'upload' => $user->u ?? 0,
            'download' => $user->d ?? 0,
            'total_used' => $user->getTotalUsedTraffic(),
            'total_available' => $trafficSummary['effective_transfer_enable'],
            'remaining' => $trafficSummary['effective_remaining_traffic'],
            'usage_percentage' => $trafficSummary['effective_transfer_enable'] > 0
                ? min(100, ($user->getTotalUsedTraffic() / $trafficSummary['effective_transfer_enable']) * 100)
                : 0,
            'next_reset_at' => $user->next_reset_at,
            'last_reset_at' => $user->last_reset_at,
            'reset_count' => $user->reset_count,
            ...$trafficSummary,
        ];
    }

    public function getTrafficSummary(User $user): array
    {
        return app(TrafficPackageService::class)->getTrafficSummary($user);
    }

    /**
     * 创建用户
     */
    public function createUser(array $data): User
    {
        $user = new User();

        // 基本信息
        $user->email = $data['email'];
        $user->password = isset($data['password'])
            ? Hash::make($data['password'])
            : Hash::make($data['email']);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();

        // 默认设置
        $user->remind_expire = admin_setting('default_remind_expire', 1);
        $user->remind_traffic = admin_setting('default_remind_traffic', 1);
        $user->expired_at = 0;

        // 可选字段
        $this->setOptionalFields($user, $data);

        // 处理计划
        if (isset($data['plan_id'])) {
            $this->setPlanForUser($user, $data['plan_id'], $data['expired_at'] ?? null);
        } else {
            $this->setTryOutPlan(user: $user);
        }

        return $user;
    }

    /**
     * 设置可选字段
     */
    private function setOptionalFields(User $user, array $data): void
    {
        $optionalFields = [
            'invite_user_id',
            'telegram_id',
            'group_id',
            'speed_limit',
            'expired_at',
            'transfer_enable'
        ];

        foreach ($optionalFields as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
    }

    /**
     * 为用户设置计划
     */
    private function setPlanForUser(User $user, int $planId, ?int $expiredAt = null): void
    {
        $plan = Plan::find($planId);
        if (!$plan)
            return;

        $user->plan_id = $plan->id;
        $user->group_id = $plan->group_id;
        $user->transfer_enable = $plan->transfer_enable * 1073741824;
        $user->speed_limit = $plan->speed_limit;

        if ($expiredAt) {
            $user->expired_at = $expiredAt;
        }
    }

    /**
     * 为用户分配一个新套餐或续费现有套餐
     *
     * @param User $user 用户模型
     * @param Plan $plan 套餐模型
     * @param int $validityDays 购买天数
     * @return User 更新后的用户模型
     */
    public function assignPlan(User $user, Plan $plan, int $validityDays): User
    {
        $user->plan_id = $plan->id;
        $user->group_id = $plan->group_id;
        $user->transfer_enable = $plan->transfer_enable * 1073741824;
        $user->speed_limit = $plan->speed_limit;

        if ($validityDays > 0) {
            $user = $this->extendSubscription($user, $validityDays);
        }

        $user->save();
        return $user;
    }

    /**
     * 延长用户的订阅有效期
     *
     * @param User $user 用户模型
     * @param int $days 延长天数
     * @return User 更新后的用户模型
     */
    public function extendSubscription(User $user, int $days): User
    {
        $currentExpired = $user->expired_at ?? time();
        $user->expired_at = max($currentExpired, time()) + ($days * 86400);

        return $user;
    }

    /**
     * 设置试用计划
     */
    private function setTryOutPlan(User $user): void
    {
        if (!(int) admin_setting('try_out_plan_id', 0))
            return;

        $plan = Plan::find(admin_setting('try_out_plan_id'));
        if (!$plan)
            return;

        $user->transfer_enable = $plan->transfer_enable * 1073741824;
        $user->plan_id = $plan->id;
        $user->group_id = $plan->group_id;
        $user->expired_at = time() + (admin_setting('try_out_hour', 1) * 3600);
        $user->speed_limit = $plan->speed_limit;
    }
}
