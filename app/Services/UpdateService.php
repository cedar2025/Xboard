<?php

namespace App\Services;

use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class UpdateService
{
    const UPDATE_CHECK_INTERVAL = 86400; // 24 hours
    const GITHUB_API_URL = 'https://api.github.com/repos/cedar2025/xboard/commits';
    const CACHE_UPDATE_INFO = 'UPDATE_INFO';
    const CACHE_LAST_CHECK = 'LAST_UPDATE_CHECK';
    const CACHE_UPDATE_LOCK = 'UPDATE_LOCK';
    const CACHE_VERSION = 'CURRENT_VERSION';
    const CACHE_VERSION_DATE = 'CURRENT_VERSION_DATE';
    
    /**
     * Get current version from cache or generate new one
     */
    public function getCurrentVersion(): string
    {
        $date = Cache::get(self::CACHE_VERSION_DATE, date('Ymd'));
        $hash = Cache::get(self::CACHE_VERSION, $this->getCurrentCommit());
        return $date . '-' . $hash;
    }

    /**
     * Update version cache
     */
    public function updateVersionCache(): void
    {
        try {
            $result = Process::run('git log -1 --format=%cd:%H --date=format:%Y%m%d');
            if ($result->successful()) {
                list($date, $hash) = explode(':', trim($result->output()));
                Cache::forever(self::CACHE_VERSION_DATE, $date);
                Cache::forever(self::CACHE_VERSION, substr($hash, 0, 7));
                Log::info('Version cache updated: ' . $date . '-' . substr($hash, 0, 7));
                return;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get version with date: ' . $e->getMessage());
        }

        // Fallback
        Cache::forever(self::CACHE_VERSION_DATE, date('Ymd'));
        Cache::forever(self::CACHE_VERSION, $this->getCurrentCommit());
        Log::info('Version cache updated (fallback): ' . date('Ymd') . '-' . $this->getCurrentCommit());
    }

    public function checkForUpdates(): array
    {
        try {
            // Get current version commit
            $currentCommit = $this->getCurrentCommit();
            if ($currentCommit === 'unknown') {
                // If unable to get current commit, try to get the first commit
                $currentCommit = $this->getFirstCommit();
            }
            // Get local git logs
            $localLogs = $this->getLocalGitLogs();
            if (empty($localLogs)) {
                Log::error('Failed to get local git logs');
                return $this->getCachedUpdateInfo();
            }

            // Get remote latest commits
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'XBoard-Update-Checker'
            ])->get(self::GITHUB_API_URL . '?per_page=50');

            if ($response->successful()) {
                $commits = $response->json();
                
                if (empty($commits) || !is_array($commits)) {
                    Log::error('Invalid GitHub response format');
                    return $this->getCachedUpdateInfo();
                }
                
                $latestCommit = $this->formatCommitHash($commits[0]['sha']);
                $currentIndex = -1;
                $updateLogs = [];
                
                // First, find the current version position in remote commit history
                foreach ($commits as $index => $commit) {
                    $shortSha = $this->formatCommitHash($commit['sha']);
                    if ($shortSha === $currentCommit) {
                        $currentIndex = $index;
                        break;
                    }
                }
                
                // Check local version status
                $isLocalNewer = false;
                if ($currentIndex === -1) {
                    // Current version not found in remote history, check local commits
                    foreach ($localLogs as $localCommit) {
                        $localHash = $this->formatCommitHash($localCommit['hash']);
                        // If latest remote commit found, local is not newer
                        if ($localHash === $latestCommit) {
                            $isLocalNewer = false;
                            break;
                        }
                        // Record additional local commits
                        $updateLogs[] = [
                            'version' => $localHash,
                            'message' => $localCommit['message'],
                            'author' => $localCommit['author'],
                            'date' => $localCommit['date'],
                            'is_local' => true
                        ];
                        $isLocalNewer = true;
                    }
                }
                
                // If local is not newer, collect commits that need to be updated
                if (!$isLocalNewer && $currentIndex > 0) {
                    $updateLogs = [];
                    // Collect all commits between current version and latest version
                    for ($i = 0; $i < $currentIndex; $i++) {
                        $commit = $commits[$i];
                        $updateLogs[] = [
                            'version' => $this->formatCommitHash($commit['sha']),
                            'message' => $commit['commit']['message'],
                            'author' => $commit['commit']['author']['name'],
                            'date' => $commit['commit']['author']['date'],
                            'is_local' => false
                        ];
                    }
                }

                $hasUpdate = !$isLocalNewer && $currentIndex > 0;
                
                $updateInfo = [
                    'has_update' => $hasUpdate,
                    'is_local_newer' => $isLocalNewer,
                    'latest_version' => $isLocalNewer ? $currentCommit : $latestCommit,
                    'current_version' => $currentCommit,
                    'update_logs' => $updateLogs,
                    'download_url' => $commits[0]['html_url'] ?? '',
                    'published_at' => $commits[0]['commit']['author']['date'] ?? '',
                    'author' => $commits[0]['commit']['author']['name'] ?? '',
                ];

                // Cache check results
                $this->setLastCheckTime();
                Cache::put(self::CACHE_UPDATE_INFO, $updateInfo, now()->addHours(24));

                return $updateInfo;
            }
            
            return $this->getCachedUpdateInfo();
        } catch (\Exception $e) {
            Log::error('Update check failed: ' . $e->getMessage());
            return $this->getCachedUpdateInfo();
        }
    }

    public function executeUpdate(): array
    {
        // Check for new version first
        $updateInfo = $this->checkForUpdates();
        if ($updateInfo['is_local_newer']) {
            return [
                'success' => false,
                'message' => __('update.local_newer')
            ];
        }
        if (!$updateInfo['has_update']) {
            return [
                'success' => false,
                'message' => __('update.already_latest')
            ];
        }

        // Check for update lock
        if (Cache::get(self::CACHE_UPDATE_LOCK)) {
            return [
                'success' => false,
                'message' => __('update.process_running')
            ];
        }

        try {
            // Set update lock
            Cache::put(self::CACHE_UPDATE_LOCK, true, now()->addMinutes(30));

            // 1. Backup database
            $this->backupDatabase();

            // 2. Pull latest code
            $result = $this->pullLatestCode();
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // 3. Run database migrations
            $this->runMigrations();

            // 4. Clear cache
            $this->clearCache();

            // 5. Create update flag
            $this->createUpdateFlag();

            // 6. Restart Octane if running
            $this->restartOctane();

            // Remove update lock
            Cache::forget(self::CACHE_UPDATE_LOCK);

            // Format update logs
            $logMessages = array_map(function($log) {
                return sprintf("- %s (%s): %s", 
                    $log['version'],
                    date('Y-m-d H:i', strtotime($log['date'])),
                    $log['message']
                );
            }, $updateInfo['update_logs']);

            return [
                'success' => true,
                'message' => __('update.success', [
                    'from' => $updateInfo['current_version'],
                    'to' => $updateInfo['latest_version']
                ]),
                'version' => $updateInfo['latest_version'],
                'update_info' => [
                    'from_version' => $updateInfo['current_version'],
                    'to_version' => $updateInfo['latest_version'],
                    'update_logs' => $logMessages,
                    'author' => $updateInfo['author'],
                    'published_at' => $updateInfo['published_at']
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Update execution failed: ' . $e->getMessage());
            Cache::forget(self::CACHE_UPDATE_LOCK);
            
            return [
                'success' => false,
                'message' => __('update.failed', ['error' => $e->getMessage()])
            ];
        }
    }

    protected function getCurrentCommit(): string
    {
        try {
            // Ensure git configuration is correct
            Process::run(sprintf('git config --global --add safe.directory %s', base_path()));
            $result = Process::run('git rev-parse HEAD');
            $fullHash = trim($result->output());
            return $fullHash ? $this->formatCommitHash($fullHash) : 'unknown';
        } catch (\Exception $e) {
            Log::error('Failed to get current commit: ' . $e->getMessage());
            return 'unknown';
        }
    }

    protected function getFirstCommit(): string
    {
        try {
            // Get first commit hash
            $result = Process::run('git rev-list --max-parents=0 HEAD');
            $fullHash = trim($result->output());
            return $fullHash ? $this->formatCommitHash($fullHash) : 'unknown';
        } catch (\Exception $e) {
            Log::error('Failed to get first commit: ' . $e->getMessage());
            return 'unknown';
        }
    }

    protected function formatCommitHash(string $hash): string
    {
        // Use 7 characters for commit hash
        return substr($hash, 0, 7);
    }

    protected function backupDatabase(): void
    {
        try {
            // Use existing backup command
            Process::run('php artisan backup:database');
            
            if (!Process::result()->successful()) {
                throw new \Exception(__('update.backup_failed', ['error' => Process::result()->errorOutput()]));
            }
        } catch (\Exception $e) {
            Log::error('Database backup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function pullLatestCode(): array
    {
        try {
            // Get current project root directory
            $basePath = base_path();
            
            // Ensure git configuration is correct
            Process::run(sprintf('git config --global --add safe.directory %s', $basePath));
            
            // Pull latest code
            Process::run('git fetch origin master');
            Process::run('git reset --hard origin/master');

            // Update dependencies
            Process::run('composer install --no-dev --optimize-autoloader');

            // Update version cache after pulling new code
            $this->updateVersionCache();

            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('update.code_update_failed', ['error' => $e->getMessage()])
            ];
        }
    }

    protected function runMigrations(): void
    {
        try {
            Process::run('php artisan migrate --force');
        } catch (\Exception $e) {
            Log::error('Migration failed: ' . $e->getMessage());
            throw new \Exception(__('update.migration_failed', ['error' => $e->getMessage()]));
        }
    }

    protected function clearCache(): void
    {
        try {
            $commands = [
                'php artisan config:clear',
                'php artisan cache:clear',
                'php artisan view:clear',
                'php artisan route:clear'
            ];

            foreach ($commands as $command) {
                Process::run($command);
            }
        } catch (\Exception $e) {
            Log::error('Cache clearing failed: ' . $e->getMessage());
            throw new \Exception(__('update.cache_clear_failed', ['error' => $e->getMessage()]));
        }
    }

    protected function createUpdateFlag(): void
    {
        try {
            // Create update flag file for external script to detect and restart container
            $flagFile = storage_path('update_pending');
            File::put($flagFile, date('Y-m-d H:i:s'));
        } catch (\Exception $e) {
            Log::error('Failed to create update flag: ' . $e->getMessage());
            throw new \Exception(__('update.flag_create_failed', ['error' => $e->getMessage()]));
        }
    }

    protected function restartOctane(): void
    {
        try {
            if (!config('octane.server')) {
                return;
            }

            // Check Octane running status
            $statusResult = Process::run('php artisan octane:status');
            if (!$statusResult->successful()) {
                Log::info('Octane is not running, skipping restart.');
                return;
            }

            $output = $statusResult->output();
            if (str_contains($output, 'Octane server is running')) {
                Log::info('Restarting Octane server after update...');
                // Update version cache before restart
                $this->updateVersionCache();
                Process::run('php artisan octane:stop');
                Log::info('Octane server restarted successfully.');
            } else {
                Log::info('Octane is not running, skipping restart.');
            }
        } catch (\Exception $e) {
            Log::error('Failed to restart Octane server: ' . $e->getMessage());
            // Non-fatal error, don't throw exception
        }
    }

    public function getLastCheckTime()
    {
        return Cache::get(self::CACHE_LAST_CHECK, null);
    }

    protected function setLastCheckTime(): void
    {
        Cache::put(self::CACHE_LAST_CHECK, now()->timestamp, now()->addDays(30));
    }

    public function getCachedUpdateInfo(): array
    {
        return Cache::get(self::CACHE_UPDATE_INFO, [
            'has_update' => false,
            'latest_version' => $this->getCurrentCommit(),
            'current_version' => $this->getCurrentCommit(),
            'update_logs' => [],
            'download_url' => '',
            'published_at' => '',
            'author' => '',
        ]);
    }

    protected function getLocalGitLogs(int $limit = 50): array
    {
        try {
            // 获取本地git log
            $result = Process::run(
                sprintf('git log -%d --pretty=format:"%%H||%%s||%%an||%%ai"', $limit)
            );

            if (!$result->successful()) {
                return [];
            }

            $logs = [];
            $lines = explode("\n", trim($result->output()));
            foreach ($lines as $line) {
                $parts = explode('||', $line);
                if (count($parts) === 4) {
                    $logs[] = [
                        'hash' => $parts[0],
                        'message' => $parts[1],
                        'author' => $parts[2],
                        'date' => $parts[3]
                    ];
                }
            }
            return $logs;
        } catch (\Exception $e) {
            Log::error('Failed to get local git logs: ' . $e->getMessage());
            return [];
        }
    }
} 