<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    protected $table = 'v2_stat';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'invite_count' => 'integer',
        'register_count' => 'integer',
        'paid_count' => 'integer',
        'commission_count' => 'integer',
        'order_count' => 'integer',
        'record_at' => 'integer',
        'paid_total' => 'integer',
        'commission_total' => 'integer',
        'order_total' => 'integer'
    ];
}
