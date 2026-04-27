<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'channel' => $this->channel,
            'arch' => $this->arch,
            'version' => $this->version,
            'build_number' => $this->build_number,
            'min_supported_build' => $this->min_supported_build,
            'download_url' => $this->download_url,
            'file_size' => $this->file_size,
            'sha256' => $this->sha256,
            'release_notes' => $this->release_notes,
            'is_force' => $this->is_force,
            'published_at' => $this->published_at,
        ];
    }
}
