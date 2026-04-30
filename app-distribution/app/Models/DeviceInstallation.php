<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceInstallation extends Model
{
    protected $fillable = [
        'app_id',
        'installation_id',
        'platform',
        'channel',
        'arch',
        'version',
        'build_number',
        'os_version',
        'ip_address',
        'user_agent',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'build_number' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(DistributionApp::class, 'app_id');
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }
}
