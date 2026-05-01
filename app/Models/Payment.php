<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'v2_payment';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'config' => 'array',
        'enable' => 'boolean',
        'sort' => 'integer',
        'handling_fee_fixed' => 'float',
        'handling_fee_percent' => 'float'
    ];

    protected $hidden = [
        'config',
    ];
}
