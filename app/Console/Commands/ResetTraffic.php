<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ResetTraffic extends Command
{
    /**
     * @var Builder
     */
    protected $builder;

    /**
     * @var string
     */
    protected $signature = 'reset:traffic';

    /**
     * @var string
     */
    protected $description = '流量清空';

    public function __construct()
    {
        parent::__construct();
        $this->builder = User::where('expired_at', '!=', NULL)
            ->where('expired_at', '>', time());
    }

    /**
     * 执行流量重置命令
     */
    public function handle()
    {
        ini_set('memory_limit', -1);

        // 按重置方法分组查询所有套餐
        $resetMethods = Plan::select(
            DB::raw("GROUP_CONCAT(`id`) as plan_ids"),
            DB::raw("reset_traffic_method as method")
        )
            ->groupBy('reset_traffic_method')
            ->get()
            ->toArray();

        // 使用闭包直接引用方法
        $resetHandlers = [
            Plan::RESET_TRAFFIC_FIRST_DAY_MONTH => fn($builder) => $this->resetByMonthFirstDay($builder),
            Plan::RESET_TRAFFIC_MONTHLY => fn($builder) => $this->resetByExpireDay($builder),
            Plan::RESET_TRAFFIC_NEVER => null,
            Plan::RESET_TRAFFIC_FIRST_DAY_YEAR => fn($builder) => $this->resetByYearFirstDay($builder),
            Plan::RESET_TRAFFIC_YEARLY => fn($builder) => $this->resetByExpireYear($builder),
        ];

        // 处理每种重置方法
        foreach ($resetMethods as $resetMethod) {
            $planIds = explode(',', $resetMethod['plan_ids']);

            // 获取重置方法
            $method = $resetMethod['method'];
            if ($method === NULL) {
                $method = (int) admin_setting('reset_traffic_method', 0);
            }

            // 跳过不重置的方法
            if ($method === 2) {
                continue;
            }

            // 获取该方法的处理器
            $handler = $resetHandlers[$method] ?? null;
            if (!$handler) {
                continue;
            }

            // 创建查询构建器并执行重置
            $userQuery = (clone $this->builder)->whereIn('plan_id', $planIds);
            $handler($userQuery);
        }
    }

    /**
     * 按用户年度到期日重置流量
     */
    private function resetByExpireYear(Builder $builder): void
    {
        $today = date('m-d');
        $this->resetUsersByDateCondition($builder, function ($user) use ($today) {
            return date('m-d', $user->expired_at) === $today;
        });
    }

    /**
     * 按新年第一天重置流量
     */
    private function resetByYearFirstDay(Builder $builder): void
    {
        $isNewYear = date('md') === '0101';
        if (!$isNewYear) {
            return;
        }

        $this->resetAllUsers($builder);
    }

    /**
     * 按月初第一天重置流量
     */
    private function resetByMonthFirstDay(Builder $builder): void
    {
        $isFirstDayOfMonth = date('d') === '01';
        if (!$isFirstDayOfMonth) {
            return;
        }

        $this->resetAllUsers($builder);
    }

    /**
     * 按用户到期日重置流量
     */
    private function resetByExpireDay(Builder $builder): void
    {
        $today = date('d');
        $lastDay = date('d', strtotime('last day of +0 months'));

        $this->resetUsersByDateCondition($builder, function ($user) use ($today, $lastDay) {
            $expireDay = date('d', $user->expired_at);
            return $expireDay === $today || ($today === $lastDay && $expireDay >= $today);
        });
    }

    /**
     * 重置所有符合条件的用户流量
     */
    private function resetAllUsers(Builder $builder): void
    {
        $this->resetUsersByDateCondition($builder, function () {
            return true;
        });
    }

    /**
     * 根据日期条件重置用户流量
     * @param Builder $builder 用户查询构建器
     * @param callable $condition 日期条件回调
     */
    private function resetUsersByDateCondition(Builder $builder, callable $condition): void
    {
        /** @var \App\Models\User[] $users */
        $users = $builder->with('plan')->get();
        $usersToUpdate = [];

        foreach ($users as $user) {
            if ($condition($user)) {
                $usersToUpdate[] = [
                    'id' => $user->id,
                    'transfer_enable' => $user->plan->transfer_enable
                ];
            }
        }

        foreach ($usersToUpdate as $userData) {
            User::where('id', $userData['id'])->update([
                'transfer_enable' => (intval($userData['transfer_enable']) * 1073741824),
                'u' => 0,
                'd' => 0
            ]);
        }
    }
}
