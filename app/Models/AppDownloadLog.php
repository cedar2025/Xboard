<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppDownloadLog extends Model
{
    protected $table = 'v2_app_download_logs';

    protected $fillable = [
        'app_id',
        'app_version_id',
        'app_artifact_id',
        'user_id',
        'ip_address',
        'user_agent',
        'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(DistributionApp::class, 'app_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AppVersion::class, 'app_version_id');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(AppArtifact::class, 'app_artifact_id');
    }
}
