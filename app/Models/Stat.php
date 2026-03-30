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
        'order_count' => 'integer',
        'order_total' => 'integer',
        'commission_count' => 'integer',
        'commission_total' => 'integer',
        'paid_count' => 'integer',
        'paid_total' => 'integer',
        'register_count' => 'integer',
        'invite_count' => 'integer'
    ];
}
