<?php

namespace App\Models;

use Dflydev\DotAccessData\Data;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\GiftCardTemplate
 *
 * @property int $id
 * @property string $name 礼品卡名称
 * @property string|null $description 礼品卡描述
 * @property int $type 卡片类型
 * @property boolean $status 状态
 * @property array|null $conditions 使用条件配置
 * @property array $rewards 奖励配置
 * @property array|null $limits 限制条件
 * @property array|null $special_config 特殊配置
 * @property string|null $icon 卡片图标
 * @property string $theme_color 主题色
 * @property int $sort 排序
 * @property int $admin_id 创建管理员ID
 * @property int $created_at
 * @property int $updated_at
 */
class GiftCardTemplate extends Model
{
    protected $table = 'v2_gift_card_template';
    protected $dateFormat = 'U';

    // 卡片类型常量
    const TYPE_GENERAL = 1;         // 通用礼品卡
    const TYPE_PLAN = 2;            // 套餐礼品卡
    const TYPE_MYSTERY = 3;         // 盲盒礼品卡

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'conditions',
        'rewards',
        'limits',
        'special_config',
        'icon',
        'background_image',
        'theme_color',
        'sort',
        'admin_id'
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'conditions' => 'array',
        'rewards' => 'array',
        'limits' => 'array',
        'special_config' => 'array',
        'status' => 'boolean'
    ];

    /**
     * 获取卡片类型映射
     */
    public static function getTypeMap(): array
    {
        return [
            self::TYPE_GENERAL => '通用礼品卡',
            self::TYPE_PLAN => '套餐礼品卡',
            self::TYPE_MYSTERY => '盲盒礼品卡',
        ];
    }

    /**
     * 获取类型名称
     */
    public function getTypeNameAttribute(): string
    {
        return self::getTypeMap()[$this->type] ?? '未知类型';
    }

    /**
     * 关联兑换码
     */
    public function codes(): HasMany
    {
        return $this->hasMany(GiftCardCode::class, 'template_id');
    }

    /**
     * 关联使用记录
     */
    public function usages(): HasMany
    {
        return $this->hasMany(GiftCardUsage::class, 'template_id');
    }

    /**
     * 关联统计数据
     */
    public function stats(): HasMany
    {
        return $this->hasMany(GiftCardUsage::class, 'template_id');
    }

    /**
     * 检查是否可用
     */
    public function isAvailable(): bool
    {
        return $this->status;
    }

    /**
     * 检查用户是否满足使用条件
     */
    public function checkUserConditions(User $user): bool
    {
        switch ($this->type) {
            case self::TYPE_GENERAL:
                $rewards = $this->rewards ?? [];
                if (isset($rewards['transfer_enable']) || isset($rewards['expire_days']) || isset($rewards['reset_package'])) {
                    if (!$user->plan_id) {
                        return false;
                    }
                }
                break;
            case self::TYPE_PLAN:
                if ($user->isActive()) {
                    return false;
                }
                break;
        }

        $conditions = $this->conditions ?? [];

        // 检查新用户条件
        if (isset($conditions['new_user_only']) && $conditions['new_user_only']) {
            $maxDays = $conditions['new_user_max_days'] ?? 7;
            if ($user->created_at < (time() - ($maxDays * 86400))) {
                return false;
            }
        }

        // 检查付费用户条件
        if (isset($conditions['paid_user_only']) && $conditions['paid_user_only']) {
            $paidOrderExists = $user->orders()->where('status', Order::STATUS_COMPLETED)->exists();
            if (!$paidOrderExists) {
                return false;
            }
        }

        // 检查允许的套餐
        if (isset($conditions['allowed_plans']) && $user->plan_id) {
            if (!in_array($user->plan_id, $conditions['allowed_plans'])) {
                return false;
            }
        }

        // 检查是否需要邀请人
        if (isset($conditions['require_invite']) && $conditions['require_invite']) {
            if (!$user->invite_user_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * 计算实际奖励
     */
    public function calculateActualRewards(User $user): array
    {
        $baseRewards = $this->rewards;
        $actualRewards = $baseRewards;

        // 处理盲盒随机奖励
        if ($this->type === self::TYPE_MYSTERY && isset($this->rewards['random_rewards'])) {
            $randomRewards = $this->rewards['random_rewards'];
            $totalWeight = array_sum(array_column($randomRewards, 'weight'));
            $random = mt_rand(1, $totalWeight);
            $currentWeight = 0;

            foreach ($randomRewards as $reward) {
                $currentWeight += $reward['weight'];
                if ($random <= $currentWeight) {
                    $actualRewards = array_merge($actualRewards, $reward);
                    unset($actualRewards['weight']);
                    break;
                }
            }
        }

        // 处理节日等特殊奖励(通用逻辑)
        if (isset($this->special_config['festival_bonus'])) {
            $now = time();
            $festivalConfig = $this->special_config;

            if (isset($festivalConfig['start_time']) && isset($festivalConfig['end_time'])) {
                if ($now >= $festivalConfig['start_time'] && $now <= $festivalConfig['end_time']) {
                    $bonus = data_get($festivalConfig, 'festival_bonus', 1.0);
                    if ($bonus > 1.0) {
                        foreach ($actualRewards as $key => &$value) {
                            if (is_numeric($value)) {
                                $value = intval($value * $bonus);
                            }
                        }
                        unset($value); // 解除引用
                    }
                }
            }
        }

        return $actualRewards;
    }

    /**
     * 检查使用频率限制
     */
    public function checkUsageLimit(User $user): bool
    {
        $conditions = $this->conditions ?? [];

        // 检查每用户最大使用次数
        if (isset($conditions['max_use_per_user'])) {
            $usedCount = $this->usages()
                ->where('user_id', $user->id)
                ->count();
            if ($usedCount >= $conditions['max_use_per_user']) {
                return false;
            }
        }

        // 检查冷却时间
        if (isset($conditions['cooldown_hours'])) {
            $lastUsage = $this->usages()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastUsage && isset($lastUsage->created_at)) {
                $cooldownTime = $lastUsage->created_at + ($conditions['cooldown_hours'] * 3600);
                if (time() < $cooldownTime) {
                    return false;
                }
            }
        }

        return true;
    }
}