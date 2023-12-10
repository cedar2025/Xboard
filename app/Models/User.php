<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

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

    // 获取用户套餐
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    // 获取用户邀请码列表
    public function codes()
    {
        return $this->hasMany(InviteCode::class, 'user_id', 'id');
    }

    // 关联工单列表
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'user_id', 'id');
    }
}
