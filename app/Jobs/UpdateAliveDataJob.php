<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use App\Services\UserOnlineService;
use Illuminate\Support\Facades\Log;

class UpdateAliveDataJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  private const CACHE_PREFIX = 'ALIVE_IP_USER_';
  private const CACHE_TTL = 120;
  private const NODE_DATA_EXPIRY = 100;

  public function __construct(
    private readonly array $data,
    private readonly string $nodeType,
    private readonly int $nodeId
  ) {
    $this->onQueue('online_sync');
  }

  public function handle(): void
  {
    try {
      $updateAt = time();
      $nowTs = time();
      $now = now();
      $nodeKey = $this->nodeType . $this->nodeId;
      $userUpdates = [];

      foreach ($this->data as $uid => $ips) {
        $cacheKey = self::CACHE_PREFIX . $uid;
        $ipsArray = Cache::get($cacheKey, []);
        $ipsArray = [
          ...collect($ipsArray)
            ->filter(fn(mixed $value): bool => is_array($value) && ($updateAt - ($value['lastupdateAt'] ?? 0) <= self::NODE_DATA_EXPIRY)),
          $nodeKey => [
            'aliveips' => $ips,
            'lastupdateAt' => $updateAt,
          ],
        ];

        $count = UserOnlineService::calculateDeviceCount($ipsArray);
        $ipsArray['alive_ip'] = $count;
        Cache::put($cacheKey, $ipsArray, now()->addSeconds(self::CACHE_TTL));

        $userUpdates[] = [
          'id' => (int) $uid,
          'count' => (int) $count,
        ];
      }

      if (!empty($userUpdates)) {
        $allIds = collect($userUpdates)
          ->pluck('id')
          ->filter()
          ->map(fn($v) => (int) $v)
          ->unique()
          ->values()
          ->all();

        if (!empty($allIds)) {
          $existingIds = User::query()
            ->whereIn('id', $allIds)
            ->pluck('id')
            ->map(fn($v) => (int) $v)
            ->all();

          if (!empty($existingIds)) {
            collect($userUpdates)
              ->filter(fn($row) => in_array((int) ($row['id'] ?? 0), $existingIds, true))
              ->chunk(1000)
              ->each(function ($chunk) use ($now) {
                collect($chunk)->each(function ($update) use ($now) {
                  $id = (int) ($update['id'] ?? 0);
                  $count = (int) ($update['count'] ?? 0);
                  if ($id > 0) {
                    User::query()
                      ->whereKey($id)
                      ->update([
                        'online_count' => $count,
                        'last_online_at' => $now,
                      ]);
                  }
                });
              });
          }
        }
      }
    } catch (\Throwable $e) {
      Log::error('UpdateAliveDataJob failed', [
        'error' => $e->getMessage(),
      ]);
      $this->fail($e);
    }
  }


}
