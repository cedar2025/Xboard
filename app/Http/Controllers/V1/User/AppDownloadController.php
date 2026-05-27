<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\AppArtifact;
use App\Services\AppDownloadVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AppDownloadController extends Controller
{
    public function prepare(Request $request, int $artifact)
    {
        $artifact = AppArtifact::with('version.app')->find($artifact);
        if (!$artifact || !$artifact->version?->isPublished() || !(bool) $artifact->version->app?->is_active) {
            return $this->fail([404, '安装包不存在或已下架']);
        }

        [$valid, $error] = app(AppDownloadVerificationService::class)->verify($request);
        if (!$valid) {
            return $this->fail($error ?: [400, '人机验证失败']);
        }

        $downloadUrl = URL::temporarySignedRoute(
            'app-downloads.download',
            now()->addSeconds(180),
            [
                'artifact' => $artifact->id,
                'user_id' => $request->user()->id,
            ],
            false
        );

        return $this->success([
            'download_url' => $downloadUrl,
            'expires_in' => 180,
        ]);
    }
}
