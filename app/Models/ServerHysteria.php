<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerHysteria extends Model
{
    protected $table = 'v2_server_hysteria';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'group_id' => 'array',
        'route_id' => 'array',
        'tags' => 'array',
        'ips' => 'array',
        'excludes' => 'array'
    ];

    // ALPN映射表
    public static $alpnMap = [
        0 => 'hysteria',
        1 => 'http/1.1',
        2 => 'h2',
        3 => 'h3'
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }
}
