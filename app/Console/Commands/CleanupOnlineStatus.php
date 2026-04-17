<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupOnlineStatus extends Command
{
    protected $signature = 'cleanup:online-status';

    protected $description = 'Reset stale online_count for users whose devices have expired from Redis';

    public function handle(): void
    {
        $affected = User::where('online_count', '>', 0)
            ->where(function ($query) {
                $query->where('last_online_at', '<', now()->subMinutes(10))
                    ->orWhereNull('last_online_at');
            })
            ->update(['online_count' => 0]);

        if ($affected > 0) {
            $this->info("Reset online_count for {$affected} stale users.");
        }
    }
}
