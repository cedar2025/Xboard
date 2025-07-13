<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\GiftCardUsage
 *
 * @property int $id
 * @property int $code_id 兑换码ID
 * @property int $template_id 模板ID
 * @property int $user_id 使用用户ID
 * @property int|null $invite_user_id 邀请人ID
 * @property array $rewards_given 实际发放的奖励
 * @property array|null $invite_rewards 邀请人获得的奖励
 * @property int|null $user_level_at_use 使用时用户等级
 * @property int|null $plan_id_at_use 使用时用户套餐ID
 * @property float $multiplier_applied 应用的倍率
 * @property string|null $ip_address 使用IP地址
 * @property string|null $user_agent 用户代理
 * @property string|null $notes 备注
 * @property int $created_at
 */
class GiftCardUsage extends Model
{
    protected $table = 'v2_gift_card_usage';
    protected $dateFormat = 'U';
    public $timestamps = false;

    protected $fillable = [
        'code_id',
        'template_id',
        'user_id',
        'invite_user_id',
        'rewards_given',
        'invite_rewards',
        'user_level_at_use',
        'plan_id_at_use',
        'multiplier_applied',
        'ip_address',
        'user_agent',
        'notes',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'rewards_given' => 'array',
        'invite_rewards' => 'array',
        'multiplier_applied' => 'float'
    ];

    /**
     * 关联兑换码
     */
    public function code(): BelongsTo
    {
        return $this->belongsTo(GiftCardCode::class, 'code_id');
    }

    /**
     * 关联模板
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(GiftCardTemplate::class, 'template_id');
    }

    /**
     * 关联使用用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联邀请人
     */
    public function inviteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invite_user_id');
    }

    /**
     * 创建使用记录
     */
    public static function createRecord(
        GiftCardCode $code,
        User $user,
        array $rewards,
        array $options = []
    ): self {
        return self::create([
            'code_id' => $code->id,
            'template_id' => $code->template_id,
            'user_id' => $user->id,
            'invite_user_id' => $user->invite_user_id,
            'rewards_given' => $rewards,
            'invite_rewards' => $options['invite_rewards'] ?? null,
            'user_level_at_use' => $user->plan ? $user->plan->sort : null,
            'plan_id_at_use' => $user->plan_id,
            'multiplier_applied' => $options['multiplier'] ?? 1.0,
            // 'ip_address' => $options['ip_address'] ?? null,
            'user_agent' => $options['user_agent'] ?? null,
            'notes' => $options['notes'] ?? null,
            'created_at' => time(),
        ]);
    }
} 