<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function hasAnyRole(array $roles): bool
    {
        if ($this->role === self::ROLE_OWNER) {
            return true;
        }

        return in_array($this->role, $roles, true);
    }

    public function canManage(): bool
    {
        return $this->hasAnyRole([self::ROLE_OWNER, self::ROLE_ADMIN]);
    }
}
