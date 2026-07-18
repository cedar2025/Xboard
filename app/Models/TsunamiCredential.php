<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TsunamiCredential extends Model
{
    protected $table = 'v2_server_tsunami_credentials';

    protected $guarded = ['id'];

    protected $hidden = [
        'credential_hash',
        'enrollment_hash',
    ];

    protected $casts = [
        'enrollment_expires_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
