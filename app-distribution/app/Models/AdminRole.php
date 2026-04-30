<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    protected $fillable = ['slug', 'name'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_role_permissions');
    }
}
