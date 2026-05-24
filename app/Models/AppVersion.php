<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AppVersion extends Model
{
    protected $table = 'v2_app_versions';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];

    protected $casts = [
        'build_number' => 'integer',
        'min_supported_build' => 'integer',
        'file_size' => 'integer',
        'is_force' => 'boolean',
        'is_enabled' => 'boolean',
        'published_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(DistributionApp::class, 'app_id');
    }

    public function artifact(): HasOne
    {
        return $this->hasOne(AppArtifact::class, 'app_version_id');
    }

    public function isPublished(): bool
    {
        return (bool) $this->is_enabled && !empty($this->published_at);
    }

    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'app_id' => $this->app_id,
            'platform' => $this->platform,
            'channel' => $this->channel,
            'arch' => $this->arch,
            'version' => $this->version,
            'build_number' => $this->build_number,
            'min_supported_build' => $this->min_supported_build,
            'download_url' => $this->download_url,
            'file_size' => $this->artifact?->file_size ?? $this->file_size,
            'sha256' => $this->artifact?->sha256 ?? $this->sha256,
            'release_notes' => $this->release_notes,
            'is_force' => $this->is_force,
            'published_at' => $this->published_at,
        ];
    }
}
