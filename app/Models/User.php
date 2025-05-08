<?php

namespace App\Models;

use App\Utils\Helper;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\User
 *
 * @property int $id 用户ID
 * @property string $email 邮箱
 * @property string $password 密码
 * @property string|null $password_algo 加密方式
 * @property string|null $password_salt 加密盐
 * @property string $token 邀请码
 * @property string $uuid
 * @property int|null $invite_user_id 邀请人
 * @property int|null $plan_id 订阅ID
 * @property int|null $group_id 权限组ID
 * @property int|null $transfer_enable 流量(KB)
 * @property int|null $speed_limit 限速Mbps
 * @property int|null $u 上行流量
 * @property int|null $d 下行流量
 * @property int|null $banned 是否封禁
 * @property int|null $remind_expire 到期提醒
 * @property int|null $remind_traffic 流量提醒
 * @property int|null $expired_at 过期时间
 * @property int|null $balance 余额
 * @property int|null $commission_balance 佣金余额
 * @property float $commission_rate 返佣比例
 * @property int|null $device_limit 设备限制数量
 * @property int|null $discount 折扣
 * @property int|null $last_login_at 最后登录时间
 * @property int|null $parent_id 父账户ID
 * @property int|null $is_admin 是否管理员
 * @property int $created_at
 * @property int $updated_at
 * @property bool $commission_auto_check 是否自动计算佣金
 *
 * @property-read User|null $invite_user 邀请人信息
 * @property-read \App\Models\Plan|null $plan 用户订阅计划
 * @property-read ServerGroup|null $group 权限组
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InviteCode> $codes 邀请码列表
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order> $orders 订单列表
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StatUser> $stat 统计信息
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets 工单列表
 * @property-read User|null $parent 父账户
 * @property-read string $subscribe_url 订阅链接（动态生成）
 */
class User extends Authenticatable
{
    use HasApiTokens;
    protected $table = 'v2_user';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'banned' => 'integer',
        'remind_expire' => 'boolean',
        'remind_traffic' => 'boolean',
        'commission_auto_check' => 'boolean',
        'commission_rate' => 'float'
    ];
    protected $hidden = ['password'];

    public const COMMISSION_TYPE_SYSTEM = 0;
    public const COMMISSION_TYPE_PERIOD = 1;
    public const COMMISSION_TYPE_ONETIME = 2;


    // 获取邀请人信息
    public function invite_user(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invite_user_id', 'id');
    }

    /**
     * 获取用户订阅计划
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ServerGroup::class, 'group_id', 'id');
    }

    // 获取用户邀请码列表
    public function codes(): HasMany
    {
        return $this->hasMany(InviteCode::class, 'user_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function stat(): HasMany
    {
        return $this->hasMany(StatUser::class, 'user_id', 'id');
    }

    // 关联工单列表
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 获取订阅链接属性
     */
    public function getSubscribeUrlAttribute(): string
    {
        return Helper::getSubscribeUrl($this->token);
    }
}
