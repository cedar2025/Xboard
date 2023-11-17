<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerVmess extends Model
{
    protected $table = 'v2_server_vmess';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'group_id' => 'array',
        'route_id' => 'array',
        'tlsSettings' => 'array',
        'networkSettings' => 'array',
        'dnsSettings' => 'array',
        'ruleSettings' => 'array',
        'tags' => 'array',
        'excludes' => 'array',
        'ips' => 'array'
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }
}
