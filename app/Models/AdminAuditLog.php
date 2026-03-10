<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $table = 'v2_admin_audit_log';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
