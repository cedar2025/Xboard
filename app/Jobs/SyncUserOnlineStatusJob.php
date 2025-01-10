<?php


namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SyncUserOnlineStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大尝试次数
     */
    public int $tries = 3;

    /**
     * 任务可以运行的最大秒数
     */
    public int $timeout = 30;

    public function __construct(
        private readonly array $updates
    ) {
    }

    /**
     * 执行任务
     */
    public function handle(): void
    {
        if (empty($this->updates)) {
            return;
        }
        collect($this->updates)
            ->chunk(1000)
            ->each(function (Collection $chunk) {
                $userIds = $chunk->pluck('id')->all();
                User::query()
                    ->whereIn('id', $userIds)
                    ->each(function (User $user) use ($chunk) {
                        $update = $chunk->firstWhere('id', $user->id);
                        if ($update) {
                            $user->update([
                                'online_count' => $update['count'],
                                'last_online_at' => now(),
                            ]);
                        }
                    });
            });
    }

    /**
     * 任务失败的处理
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Failed to sync user online status', [
            'error' => $exception->getMessage(),
            'updates_count' => count($this->updates)
        ]);
    }
}