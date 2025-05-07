<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * App\Models\ServerGroup
 *
 * @property int $id
 * @property string $name 分组名
 * @property int $created_at
 * @property int $updated_at
 * @property-read int $server_count 服务器数量
 */
class ServerGroup extends Model
{
    protected $table = 'v2_server_group';
    protected $dateFormat = 'U';
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'group_id', 'id');
    }

    public function servers()
    {
        return Server::whereJsonContains('group_ids', (string) $this->id)->get();
    }

    /**
     * 获取服务器数量
     */
    protected function serverCount(): Attribute
    {
        return Attribute::make(
            get: fn () => Server::whereJsonContains('group_ids', (string) $this->id)->count(),
        );
    }
}
