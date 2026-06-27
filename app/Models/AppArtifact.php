<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppArtifact extends Model
{
    protected $table = 'v2_app_artifacts';

    protected $fillable = [
        'app_version_id',
        'disk',
        'path',
        'original_name',
        'extension',
        'mime_type',
        'file_size',
        'sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(AppVersion::class, 'app_version_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(AppDownloadLog::class, 'app_artifact_id');
    }
}
