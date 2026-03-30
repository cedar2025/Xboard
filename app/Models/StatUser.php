<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\StatUser
 *
 * @property int $id
 * @property int $user_id 用户ID
 * @property int|null $server_id 节点ID (nullable for legacy data)
 * @property int $u 上行流量
 * @property int $d 下行流量
 * @property int $record_at 记录时间
 * @property int $created_at
 * @property int $updated_at
 * @property-read int $value 通过SUM(u + d)计算的总流量值，仅在查询指定时可用
 */
class StatUser extends Model
{
    protected $table = 'v2_stat_user';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    /**
     * Get the server that this traffic stat belongs to
     */
    public function server()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
