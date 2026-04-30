<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
