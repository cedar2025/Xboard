<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpdateEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'app_id',
        'app_version_id',
        'installation_id',
        'event',
        'platform',
        'channel',
        'from_version',
        'from_build',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'from_build' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
