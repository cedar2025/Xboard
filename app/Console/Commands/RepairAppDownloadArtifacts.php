<?php

namespace App\Console\Commands;

use App\Models\AppArtifact;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class RepairAppDownloadArtifacts extends Command
{
    protected $signature = 'app-downloads:repair-artifacts
        {--apply : Update missing artifact records when a unique candidate is found}
        {--scan=* : Additional absolute directories to scan for existing package files}';

    protected $description = 'Audit app download artifacts and rebind missing records to existing package files';

    private const DISK = 'app_downloads';
    private const EXTENSIONS = ['apk', 'dmg', 'pkg', 'exe', 'msi', 'msix', 'zip'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $appDownloadsRoot = realpath($this->appDownloadsRoot());

        if (!$appDownloadsRoot || !is_dir($appDownloadsRoot)) {
            $this->error('App downloads storage directory does not exist.');
            return self::FAILURE;
        }

        if (!$apply) {
            $this->warn('DRY RUN: no database records will be changed. Use --apply to update unique matches.');
        }

        $scanRoots = $this->scanRoots($appDownloadsRoot);
        $candidates = $this->scanPackageFiles($scanRoots, $appDownloadsRoot);

        $this->info('Scanned package files: ' . count($candidates));

        $stats = [
            'checked' => 0,
            'missing' => 0,
            'repaired' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'external' => 0,
        ];

        AppArtifact::query()
            ->with('version.app')
            ->orderBy('id')
            ->chunkById(100, function ($artifacts) use (&$stats, $candidates, $apply) {
                foreach ($artifacts as $artifact) {
                    $stats['checked']++;

                    if ($this->artifactFileExists($artifact)) {
                        continue;
                    }

                    $stats['missing']++;
                    $this->line('');
                    $this->warn($this->describeMissingArtifact($artifact));

                    $matches = $this->findCandidates($artifact, $candidates);
                    if (count($matches) !== 1) {
                        $key = count($matches) === 0 ? 'unmatched' : 'ambiguous';
                        $stats[$key]++;
                        $this->line('Candidate count: ' . count($matches));
                        foreach ($matches as $match) {
                            $this->line(' - ' . $match['absolute_path']);
                        }
                        continue;
                    }

                    $match = $matches[0];
                    $this->line('Unique candidate: ' . $match['absolute_path']);

                    if (!$this->isCandidateBindable($match)) {
                        $stats['external']++;
                        $this->warn('Candidate is outside the configured app_downloads storage root; move it into storage/app/app-downloads before applying.');
                        continue;
                    }

                    if ($apply) {
                        $this->repairArtifact($artifact, $match);
                        $stats['repaired']++;
                        $this->info('Repaired artifact #' . $artifact->id . ' -> ' . $match['relative_path']);
                    } else {
                        $this->line('Would repair artifact #' . $artifact->id . ' -> ' . $match['relative_path']);
                    }
                }
            });

        $this->line('');
        $this->info(sprintf(
            'Checked: %d, missing: %d, repaired: %d, unmatched: %d, ambiguous: %d, external-only: %d',
            $stats['checked'],
            $stats['missing'],
            $stats['repaired'],
            $stats['unmatched'],
            $stats['ambiguous'],
            $stats['external']
        ));

        return self::SUCCESS;
    }

    private function appDownloadsRoot(): string
    {
        return config('filesystems.disks.app_downloads.root', storage_path('app/app-downloads'));
    }

    private function scanRoots(string $appDownloadsRoot): array
    {
        $roots = [$appDownloadsRoot];

        foreach ((array) $this->option('scan') as $scanPath) {
            $scanPath = trim((string) $scanPath);
            if ($scanPath === '') {
                continue;
            }

            if (!$this->isAbsolutePath($scanPath)) {
                $this->warn('Skipping non-absolute scan path: ' . $scanPath);
                continue;
            }

            $realPath = realpath($scanPath);
            if (!$realPath || !is_dir($realPath)) {
                $this->warn('Skipping missing scan directory: ' . $scanPath);
                continue;
            }

            $roots[] = $realPath;
        }

        return array_values(array_unique($roots));
    }

    private function scanPackageFiles(array $roots, string $appDownloadsRoot): array
    {
        $files = [];
        $seen = [];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                if (!in_array($extension, self::EXTENSIONS, true)) {
                    continue;
                }

                $realPath = $file->getRealPath();
                if (!$realPath || isset($seen[$realPath])) {
                    continue;
                }

                $sha256 = hash_file('sha256', $realPath);
                if (!is_string($sha256)) {
                    $this->warn('Skipping unreadable package file: ' . $realPath);
                    continue;
                }

                $seen[$realPath] = true;
                $files[] = [
                    'absolute_path' => $realPath,
                    'relative_path' => $this->relativePathForDisk($realPath, $appDownloadsRoot),
                    'original_name' => $file->getFilename(),
                    'extension' => $extension,
                    'file_size' => $file->getSize(),
                    'sha256' => $sha256,
                ];
            }
        }

        return $files;
    }

    private function findCandidates(AppArtifact $artifact, array $candidates): array
    {
        $shaMatches = array_values(array_filter($candidates, function (array $candidate) use ($artifact) {
            return (bool) $artifact->sha256
                && hash_equals($artifact->sha256, $candidate['sha256']);
        }));

        if (!empty($shaMatches)) {
            return $shaMatches;
        }

        $expectedExtension = strtolower($artifact->extension ?: pathinfo($artifact->original_name, PATHINFO_EXTENSION));

        return array_values(array_filter($candidates, function (array $candidate) use ($artifact, $expectedExtension) {
            return $candidate['original_name'] === $artifact->original_name
                && (int) $candidate['file_size'] === (int) $artifact->file_size
                && $candidate['extension'] === $expectedExtension;
        }));
    }

    private function isCandidateBindable(array $candidate): bool
    {
        return !empty($candidate['relative_path']);
    }

    private function repairArtifact(AppArtifact $artifact, array $match): void
    {
        $artifact->forceFill([
            'disk' => self::DISK,
            'path' => $match['relative_path'],
            'file_size' => $match['file_size'],
            'sha256' => $match['sha256'],
        ])->save();
    }

    private function artifactFileExists(AppArtifact $artifact): bool
    {
        if (!Storage::disk($artifact->disk)->exists($artifact->path)) {
            return false;
        }

        $path = Storage::disk($artifact->disk)->path($artifact->path);

        return is_file($path);
    }

    private function relativePathForDisk(string $absolutePath, string $appDownloadsRoot): ?string
    {
        $root = rtrim(str_replace('\\', '/', $appDownloadsRoot), '/');
        $path = str_replace('\\', '/', $absolutePath);

        if (!str_starts_with($path, $root . '/')) {
            return null;
        }

        return substr($path, strlen($root) + 1);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private function describeMissingArtifact(AppArtifact $artifact): string
    {
        $version = $artifact->version;
        $app = $version?->app;

        return sprintf(
            'Missing artifact #%d app=%s version=%s platform=%s path=%s original=%s',
            $artifact->id,
            $app?->app_key ?: $app?->name ?: '-',
            $version?->version ?: '-',
            $version?->platform ?: '-',
            $artifact->path,
            $artifact->original_name
        );
    }
}
