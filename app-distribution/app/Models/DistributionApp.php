<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionApp extends Model
{
    protected $table = 'apps';

    protected $fillable = [
        'name',
        'app_key',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(AppVersion::class, 'app_id');
    }
}
