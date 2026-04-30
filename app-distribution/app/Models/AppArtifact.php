<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppArtifact extends Model
{
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
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }
}
