<?php

namespace App\Services;

use App\Models\AppArtifact;
use App\Models\AppVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArtifactStorage
{
    private const ALLOWED_EXTENSIONS = ['dmg', 'pkg', 'exe', 'msi', 'zip'];
    private const MAX_BYTES = 1024 * 1024 * 1024 * 2;

    public function store(AppVersion $version, UploadedFile $file, ?int $adminId): AppArtifact
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('仅支持 DMG、PKG、EXE、MSI、ZIP 安装包');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('安装包不能超过 2GB');
        }

        $relativePath = implode('/', [
            $version->app_id,
            $version->platform,
            $version->channel,
            $version->build_number . '-' . Str::random(12) . '.' . $extension,
        ]);

        Storage::disk('artifacts')->put($relativePath, file_get_contents($file->getRealPath()));
        $absolutePath = Storage::disk('artifacts')->path($relativePath);

        return AppArtifact::updateOrCreate(
            ['app_version_id' => $version->id],
            [
                'disk' => 'artifacts',
                'path' => $relativePath,
                'original_name' => $file->getClientOriginalName(),
                'extension' => $extension,
                'mime_type' => $file->getMimeType(),
                'file_size' => filesize($absolutePath),
                'sha256' => hash_file('sha256', $absolutePath),
                'uploaded_by' => $adminId,
            ]
        );
    }

    public function absolutePath(AppArtifact $artifact): string
    {
        return Storage::disk($artifact->disk)->path($artifact->path);
    }
}
