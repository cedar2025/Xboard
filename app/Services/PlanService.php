<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Collection;

class PlanService
{
    public Plan $plan;

    public function __construct(Plan $plan)
    {
        $this->plan = $plan;
    }

    /**
     * 获取所有可销售的订阅计划列表
     * 条件：show 和 sell 为 true，且容量充足
     *
     * @return Collection
     */
    public function getAvailablePlans(): Collection
    {
        return $this->getSellablePlans()
            ->filter(function (Plan $plan) {
                return !$this->isTrafficPackagePlan($plan);
            })
            ->values();
    }

    public function getAvailablePlansForUser(User $user): Collection
    {
        return $this->getSellablePlans()
            ->filter(function (Plan $plan) use ($user) {
                return !$this->isTrafficPackagePlan($plan) || $this->hasActiveTimeSubscription($user);
            })
            ->values();
    }

    protected function getSellablePlans(): Collection
    {
        return Plan::where('show', true)
            ->where('sell', true)
            ->orderBy('sort')
            ->get()
            ->filter(function (Plan $plan) {
                return $this->hasCapacity($plan);
            });
    }

    public function isTrafficPackagePlan(Plan $plan): bool
    {
        $prices = $plan->prices ?? [];
        $hasOnetimePrice = isset($prices[Plan::PERIOD_ONETIME]) && (float) $prices[Plan::PERIOD_ONETIME] > 0;
        $hasStandardPrice = collect([
            Plan::PERIOD_MONTHLY,
            Plan::PERIOD_QUARTERLY,
            Plan::PERIOD_HALF_YEARLY,
            Plan::PERIOD_YEARLY,
            Plan::PERIOD_TWO_YEARLY,
            Plan::PERIOD_THREE_YEARLY,
        ])->contains(function (string $period) use ($prices) {
            return isset($prices[$period]) && (float) $prices[$period] > 0;
        });

        return $hasOnetimePrice && !$hasStandardPrice;
    }

    public function hasActiveTimeSubscription(User $user): bool
    {
        return !$user->banned
            && (bool) $user->plan_id
            && (int) $user->transfer_enable > 0
            && $user->expired_at !== NULL
            && $user->expired_at > time();
    }

    /**
     * 获取指定订阅计划的可用状态
     * 条件：renew 和 sell 为 true
     *
     * @param int $planId
     * @return Plan|null
     */
    public function getAvailablePlan(int $planId): ?Plan
    {
        return Plan::where('id', $planId)
            ->where('sell', true)
            ->where('renew', true)
            ->first();
    }

    /**
     * 检查指定计划是否可用于指定用户
     * 
     * @param Plan $plan
     * @param User $user
     * @return bool
     */
    public function isPlanAvailableForUser(Plan $plan, User $user): bool
    {
        if ($this->isTrafficPackagePlan($plan)) {
            return $plan->show && $plan->sell && $this->hasCapacity($plan) && $this->hasActiveTimeSubscription($user);
        }

        // 如果是续费
        if ($user->plan_id === $plan->id) {
            return $plan->renew;
        }

        // 如果是新购
        return $plan->show && $plan->sell && $this->hasCapacity($plan);
    }

    public function validatePurchase(User $user, string $period): void
    {
        if (!$this->plan) {
            throw new ApiException(__('Subscription plan does not exist'));
        }

        // 转换周期格式为新版格式
        $periodKey = self::getPeriodKey($period);
        $price = $this->plan->prices[$periodKey] ?? null;

        if ($price === null) {
            throw new ApiException(__('This payment period cannot be purchased, please choose another period'));
        }

        if ($periodKey === Plan::PERIOD_RESET_TRAFFIC) {
            $this->validateResetTrafficPurchase($user);
            return;
        }

        if ($periodKey === Plan::PERIOD_ONETIME) {
            $this->validateOnetimeTrafficPackagePurchase($user);
            return;
        }

        if ($user->plan_id !== $this->plan->id && !$this->hasCapacity($this->plan)) {
            throw new ApiException(__('Current product is sold out'));
        }

        $this->validatePlanAvailability($user);
    }

    /**
     * 智能转换周期格式为新版格式
     * 如果是新版格式直接返回，如果是旧版格式则转换为新版格式
     *
     * @param string $period
     * @return string
     */
    public static function getPeriodKey(string $period): string
    {
        // 如果是新版格式直接返回
        if (in_array($period, self::getNewPeriods())) {
            return $period;
        }

        // 如果是旧版格式则转换为新版格式
        return Plan::LEGACY_PERIOD_MAPPING[$period] ?? $period;
    }
    /**
     * 只能转换周期格式为旧版本
     */
    public static function convertToLegacyPeriod(string $period): string
    {
        $flippedMapping = array_flip(Plan::LEGACY_PERIOD_MAPPING);
        return $flippedMapping[$period] ?? $period;
    }

    /**
     * 获取所有支持的新版周期格式
     *
     * @return array
     */
    public static function getNewPeriods(): array
    {
        return array_values(Plan::LEGACY_PERIOD_MAPPING);
    }

    /**
     * 获取旧版周期格式
     *
     * @param string $period
     * @return string
     */
    public static function getLegacyPeriod(string $period): string
    {
        $flipped = array_flip(Plan::LEGACY_PERIOD_MAPPING);
        return $flipped[$period] ?? $period;
    }

    protected function validateResetTrafficPurchase(User $user): void
    {
        if (!app(UserService::class)->isAvailable($user) || $this->plan->id !== $user->plan_id) {
            throw new ApiException(__('Subscription has expired or no active subscription, unable to purchase Data Reset Package'));
        }
    }

    protected function validateOnetimeTrafficPackagePurchase(User $user): void
    {
        if (!$this->plan->show || !$this->plan->sell) {
            throw new ApiException(__('This subscription has been sold out, please choose another subscription'));
        }

        if (
            $user->banned
            || !$user->plan_id
            || !$user->transfer_enable
            || $user->expired_at === NULL
            || $user->expired_at <= time()
        ) {
            throw new ApiException(__('Subscription has expired or no active subscription, unable to purchase Data Reset Package'));
        }
    }

    protected function validatePlanAvailability(User $user): void
    {
        if ((!$this->plan->show && !$this->plan->renew) || (!$this->plan->show && $user->plan_id !== $this->plan->id)) {
            throw new ApiException(__('This subscription has been sold out, please choose another subscription'));
        }

        if (!$this->plan->renew && $user->plan_id == $this->plan->id) {
            throw new ApiException(__('This subscription cannot be renewed, please change to another subscription'));
        }

        if (!$this->plan->show && $this->plan->renew && !app(UserService::class)->isAvailable($user)) {
            throw new ApiException(__('This subscription has expired, please change to another subscription'));
        }
    }

    public function hasCapacity(Plan $plan): bool
    {
        if ($plan->capacity_limit === null) {
            return true;
        }

        $activeUserCount = User::where('plan_id', $plan->id)
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                    ->orWhereNull('expired_at');
            })
            ->count();

        return ($plan->capacity_limit - $activeUserCount) > 0;
    }

    public function getAvailablePeriods(Plan $plan): array
    {
        return array_filter(
            $plan->getActivePeriods(),
            fn($period) => isset($plan->prices[$period]) && $plan->prices[$period] > 0
        );
    }

    public function canResetTraffic(Plan $plan): bool
    {
        return $plan->reset_traffic_method !== Plan::RESET_TRAFFIC_NEVER
            && $plan->getResetTrafficPrice() > 0;
    }
}
