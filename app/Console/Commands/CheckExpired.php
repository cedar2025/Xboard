<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\User;
use App\Services\NodeSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckExpired extends Command
{
    protected $signature = 'check:expired {--dry-run : Only report expired users without pushing or marking}';

    protected $description = '检查已过期用户并通知节点移除';

    private const BATCH_SIZE = 500;

    private const NOTIFIED_TTL = 7 * 86400;

    public function handle(): int
    {
        if (!(int) admin_setting('expiry_remove_enable', 1)) {
            $this->info('Expiry removal is disabled.');

            return self::SUCCESS;
        }

        $graceSeconds = max(0, (int) admin_setting('expiry_grace_seconds', 60));

        $users = User::toBase()
            ->whereNotNull('expired_at')
            ->whereNotNull('group_id')
            ->where('expired_at', '<=', time() - $graceSeconds)
            ->select(['id', 'group_id', 'expired_at'])
            ->get();

        if ($users->isEmpty()) {
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $notifiedUsers = 0;
        $skippedUsers = 0;
        $notifiedNodes = 0;

        foreach ($users->groupBy('group_id') as $groupId => $groupUsers) {
            $pending = $groupUsers->filter(function (object $user) use (&$skippedUsers): bool {
                if (Cache::has($this->notifiedKey($user))) {
                    $skippedUsers++;

                    return false;
                }

                return true;
            });

            if ($pending->isEmpty()) {
                continue;
            }

            $servers = Server::whereJsonContains('group_ids', (string) $groupId)
                ->where('enabled', true)
                ->get();

            $onlineServers = $servers->filter(
                fn (Server $server): bool => NodeSyncService::isNodeOnline($server->id)
            );

            if ($onlineServers->isEmpty()) {
                $this->line("group {$groupId}: {$pending->count()} expired user(s), no online node; retry later.");

                continue;
            }

            if ($dryRun) {
                $this->line("group {$groupId}: would remove {$pending->count()} user(s) from {$onlineServers->count()} node(s).");
                $notifiedUsers += $pending->count();
                $notifiedNodes += $onlineServers->count();

                continue;
            }

            $payloadUsers = $pending
                ->pluck('id')
                ->map(fn ($id): array => ['id' => (int) $id])
                ->values()
                ->all();

            foreach ($onlineServers as $server) {
                foreach (array_chunk($payloadUsers, self::BATCH_SIZE) as $chunk) {
                    NodeSyncService::push($server->id, 'sync.user.delta', [
                        'action' => 'remove',
                        'users' => $chunk,
                    ]);
                }
                $notifiedNodes++;
            }

            foreach ($pending as $user) {
                Cache::put($this->notifiedKey($user), 1, self::NOTIFIED_TTL);
            }
            $notifiedUsers += $pending->count();
        }

        if ($notifiedUsers > 0 || $skippedUsers > 0) {
            $message = sprintf(
                'Expired users: %d notified, %d already notified, %d node(s) targeted%s.',
                $notifiedUsers,
                $skippedUsers,
                $notifiedNodes,
                $dryRun ? ' (dry-run)' : ''
            );
            $this->info($message);
            Log::info($message);
        }

        return self::SUCCESS;
    }

    private function notifiedKey(object $user): string
    {
        return 'expiry_removed:' . $user->id . ':' . $user->expired_at;
    }
}
