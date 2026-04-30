<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHeartbeat extends Model
{
    protected $fillable = [
        'device_installation_id',
        'version',
        'build_number',
        'os_version',
        'reported_at',
    ];

    protected $casts = [
        'build_number' => 'integer',
        'reported_at' => 'datetime',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(DeviceInstallation::class, 'device_installation_id');
    }
}
