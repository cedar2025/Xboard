<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Order
 *
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property int|null $payment_id
 * @property int $period
 * @property string $trade_no
 * @property int $total_amount
 * @property int|null $handling_amount
 * @property int|null $balance_amount
 * @property int $type
 * @property int $status
 * @property array|null $surplus_order_ids
 * @property int|null $coupon_id
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $commission_status
 * @property int|null $invite_user_id
 * @property int|null $actual_commission_balance
 * @property int|null $commission_rate
 * @property int|null $commission_auto_check
 * 
 * @property-read Plan $plan
 * @property-read Payment|null $payment
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommissionLog> $commission_log
 */
class Order extends Model
{
    protected $table = 'v2_order';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'surplus_order_ids' => 'array',
        'handling_amount' => 'integer'
    ];

    const STATUS_PENDING = 0; // 待支付
    const STATUS_PROCESSING = 1; // 开通中
    const STATUS_CANCELLED = 2; // 已取消
    const STATUS_COMPLETED = 3; // 已完成
    const STATUS_DISCOUNTED = 4; // 已折抵

    public static $statusMap = [
        self::STATUS_PENDING => '待支付',
        self::STATUS_PROCESSING => '开通中',
        self::STATUS_CANCELLED => '已取消',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_DISCOUNTED => '已折抵',
    ];

    const TYPE_NEW_PURCHASE = 1; // 新购
    const TYPE_RENEWAL = 2; // 续费
    const TYPE_UPGRADE = 3; // 升级
    const TYPE_RESET_TRAFFIC = 4; //流量重置包
    public static $typeMap = [
        self::TYPE_NEW_PURCHASE => '新购',
        self::TYPE_RENEWAL => '续费',
        self::TYPE_UPGRADE => '升级',
        self::TYPE_RESET_TRAFFIC => '流量重置',
    ];

    /**
     * 获取与订单关联的支付方式
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    /**
     * 获取与订单关联的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取与订单关联的套餐
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * 获取与订单关联的佣金记录
     */
    public function commission_log(): HasMany
    {
        return $this->hasMany(CommissionLog::class, 'trade_no', 'trade_no');
    }
}
