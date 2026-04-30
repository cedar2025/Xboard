<?php

namespace App\Http\Controllers;

use App\Models\AppArtifact;
use App\Models\DownloadLog;
use App\Services\ArtifactStorage;
use Illuminate\Http\Request;

class ArtifactDownloadController extends Controller
{
    public function download(Request $request, AppArtifact $artifact, ArtifactStorage $storage)
    {
        $artifact->load('version.app');
        abort_unless($artifact->version->isPublished(), 404);

        DownloadLog::create([
            'app_id' => $artifact->version->app_id,
            'app_version_id' => $artifact->version->id,
            'app_artifact_id' => $artifact->id,
            'installation_id' => $request->query('installation_id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'downloaded_at' => now(),
        ]);

        return response()->download(
            $storage->absolutePath($artifact),
            $artifact->original_name,
            ['Content-Type' => $artifact->mime_type ?: 'application/octet-stream']
        );
    }
}
