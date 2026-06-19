<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTrafficPackage extends Model
{
    protected $table = 'v2_user_traffic_packages';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DEPLETED = 'depleted';

    protected $casts = [
        'total_bytes' => 'integer',
        'remaining_bytes' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'depleted_at' => 'timestamp',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }
}
