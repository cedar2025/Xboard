<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CleanupExpiredOnlineStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:expired-online-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset online_count to 0 for users stale for 5+ minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $affected = 0;
            User::query()
                ->where('online_count', '>', 0)
                ->where('last_online_at', '<', now()->subMinutes(5))
                ->chunkById(1000, function ($users) use (&$affected) {
                    if ($users->isEmpty()) {
                        return;
                    }
                    $count = User::whereIn('id', $users->pluck('id'))
                        ->update(['online_count' => 0]);
                    $affected += $count;
                }, 'id');

            $this->info("Expired online status cleaned. Affected: {$affected}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('CleanupExpiredOnlineStatus failed', ['error' => $e->getMessage()]);
            $this->error('Cleanup failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
