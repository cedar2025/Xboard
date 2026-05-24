<?php

namespace App\Services;

use App\Models\AppArtifact;
use App\Models\AppVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AppArtifactStorage
{
    private const DISK = 'app_downloads';
    private const ALLOWED_EXTENSIONS = ['apk', 'dmg', 'pkg', 'exe', 'msi', 'msix', 'zip'];
    private const MAX_BYTES = 1024 * 1024 * 1024 * 2;

    public function store(AppVersion $version, UploadedFile $file, ?int $userId): AppArtifact
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('仅支持 APK、DMG、PKG、EXE、MSI、MSIX、ZIP 安装包');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('安装包不能超过 2GB');
        }

        $relativePath = implode('/', [
            $version->app_id,
            $version->platform,
            $version->channel,
            $version->build_number . '-' . Str::random(16) . '.' . $extension,
        ]);

        $existingArtifact = $version->artifact;
        Storage::disk(self::DISK)->put($relativePath, file_get_contents($file->getRealPath()));
        $absolutePath = Storage::disk(self::DISK)->path($relativePath);

        $artifact = AppArtifact::updateOrCreate(
            ['app_version_id' => $version->id],
            [
                'disk' => self::DISK,
                'path' => $relativePath,
                'original_name' => $file->getClientOriginalName(),
                'extension' => $extension,
                'mime_type' => $file->getMimeType(),
                'file_size' => filesize($absolutePath),
                'sha256' => hash_file('sha256', $absolutePath),
                'uploaded_by' => $userId,
            ]
        );

        if ($existingArtifact && $existingArtifact->path !== $relativePath) {
            $this->deleteFile($existingArtifact);
        }

        return $artifact;
    }

    public function absolutePath(AppArtifact $artifact): string
    {
        return Storage::disk($artifact->disk)->path($artifact->path);
    }

    public function deleteFile(AppArtifact $artifact): void
    {
        Storage::disk($artifact->disk)->delete($artifact->path);
    }
}
