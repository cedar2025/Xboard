<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrafficPackage extends Model
{
    protected $table = 'v2_traffic_packages';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];

    protected $casts = [
        'transfer_enable' => 'integer',
        'price' => 'float',
        'group_id' => 'integer',
        'speed_limit' => 'integer',
        'device_limit' => 'integer',
        'show' => 'boolean',
        'sell' => 'boolean',
        'sort' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'traffic_package_id', 'id');
    }

    public function userTrafficPackages(): HasMany
    {
        return $this->hasMany(UserTrafficPackage::class, 'traffic_package_id', 'id');
    }
}
