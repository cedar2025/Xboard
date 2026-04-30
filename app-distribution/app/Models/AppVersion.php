<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AppVersion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'app_id',
        'platform',
        'channel',
        'arch',
        'version',
        'build_number',
        'min_supported_build',
        'release_notes',
        'is_force',
        'status',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'build_number' => 'integer',
        'min_supported_build' => 'integer',
        'is_force' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(DistributionApp::class, 'app_id');
    }

    public function artifact(): HasOne
    {
        return $this->hasOne(AppArtifact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
