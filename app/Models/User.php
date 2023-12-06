<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'v2_user';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];


    // 获取邀请人信息
    public function invite_user()
    {
        return $this->belongsTo(self::class, 'invite_user_id', 'id');
    }
}
