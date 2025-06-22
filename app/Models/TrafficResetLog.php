<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 流量重置记录模型
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property string $reset_type 重置类型
 * @property \Carbon\Carbon $reset_time 重置时间
 * @property int $old_upload 重置前上传流量
 * @property int $old_download 重置前下载流量
 * @property int $old_total 重置前总流量
 * @property int $new_upload 重置后上传流量
 * @property int $new_download 重置后下载流量
 * @property int $new_total 重置后总流量
 * @property string $trigger_source 触发来源
 * @property array|null $metadata 额外元数据
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read User $user 关联用户
 */
class TrafficResetLog extends Model
{
    protected $table = 'v2_traffic_reset_logs';
    
    protected $fillable = [
        'user_id',
        'reset_type',
        'reset_time',
        'old_upload',
        'old_download',
        'old_total',
        'new_upload',
        'new_download',
        'new_total',
        'trigger_source',
        'metadata',
    ];

    protected $casts = [
        'reset_time' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 重置类型常量
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_FIRST_DAY_MONTH = 'first_day_month';
    public const TYPE_YEARLY = 'yearly';
    public const TYPE_FIRST_DAY_YEAR = 'first_day_year';
    public const TYPE_MANUAL = 'manual';
    public const TYPE_PURCHASE = 'purchase';

    // 触发来源常量
    public const SOURCE_AUTO = 'auto';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_API = 'api';
    public const SOURCE_CRON = 'cron';
    public const SOURCE_USER_ACCESS = 'user_access';

    /**
     * 获取重置类型的多语言名称
     */
    public static function getResetTypeNames(): array
    {
        return [
            self::TYPE_MONTHLY => __('traffic_reset.reset_type.monthly'),
            self::TYPE_FIRST_DAY_MONTH => __('traffic_reset.reset_type.first_day_month'),
            self::TYPE_YEARLY => __('traffic_reset.reset_type.yearly'),
            self::TYPE_FIRST_DAY_YEAR => __('traffic_reset.reset_type.first_day_year'),
            self::TYPE_MANUAL => __('traffic_reset.reset_type.manual'),
            self::TYPE_PURCHASE => __('traffic_reset.reset_type.purchase'),
        ];
    }

    /**
     * 获取触发来源的多语言名称
     */
    public static function getSourceNames(): array
    {
        return [
            self::SOURCE_AUTO => __('traffic_reset.source.auto'),
            self::SOURCE_MANUAL => __('traffic_reset.source.manual'),
            self::SOURCE_API => __('traffic_reset.source.api'),
            self::SOURCE_CRON => __('traffic_reset.source.cron'),
            self::SOURCE_USER_ACCESS => __('traffic_reset.source.user_access'),
        ];
    }

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取重置类型名称
     */
    public function getResetTypeName(): string
    {
        return self::getResetTypeNames()[$this->reset_type] ?? $this->reset_type;
    }

    /**
     * 获取触发来源名称
     */
    public function getSourceName(): string
    {
        return self::getSourceNames()[$this->trigger_source] ?? $this->trigger_source;
    }

    /**
     * 获取重置的流量差值
     */
    public function getTrafficDiff(): array
    {
        return [
            'upload_diff' => $this->new_upload - $this->old_upload,
            'download_diff' => $this->new_download - $this->old_download,
            'total_diff' => $this->new_total - $this->old_total,
        ];
    }

    /**
     * 格式化流量大小
     */
    public function formatTraffic(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
} 