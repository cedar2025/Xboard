<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'v2_order';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'surplus_order_ids' => 'array'
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

}
