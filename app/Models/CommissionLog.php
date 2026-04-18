<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionLog extends Model
{
    protected $table = 'v2_commission_log';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'invite_user_id' => 'integer',
        'user_id' => 'integer',
        'get_amount' => 'integer',
        'order_amount' => 'integer'
    ];
}
