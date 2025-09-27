<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $version
 * @property string $author
 * @property string $url
 * @property string $email
 * @property string $license
 * @property string $requires
 * @property string $config
 * @property string $type
 * @property boolean $is_enabled    
 */
class Plugin extends Model
{
    protected $table = 'v2_plugins';

    const TYPE_FEATURE = 'feature';
    const TYPE_PAYMENT = 'payment';

    // 默认不可删除的插件列表
    const PROTECTED_PLUGINS = [
        'epay',           // EPay
        'alipay_f2f',     // Alipay F2F
        'btcpay',         // BTCPay
        'coinbase',       // Coinbase
        'coin_payments',  // CoinPayments
        'mgate',          // MGate
        'telegram',       // Telegram
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isFeaturePlugin(): bool
    {
        return $this->type === self::TYPE_FEATURE;
    }

    public function isPaymentPlugin(): bool
    {
        return $this->type === self::TYPE_PAYMENT;
    }

    public function isProtected(): bool
    {
        return in_array($this->code, self::PROTECTED_PLUGINS);
    }

    public function canBeDeleted(): bool
    {
        return !$this->isProtected();
    }

}
