<?php


namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Jobs\SyncUserOnlineStatusJob;

class UserOnlineService
{
    /**
     * 缓存相关常量
     */
    private const CACHE_PREFIX = 'ALIVE_IP_USER_';
    private const CACHE_TTL = 120;
    private const NODE_DATA_EXPIRY = 100;

    /**
     * 获取所有限制设备用户的在线数量
     */
    public function getAliveList(Collection $deviceLimitUsers): array
    {
        if ($deviceLimitUsers->isEmpty()) {
            return [];
        }

        $cacheKeys = $deviceLimitUsers->pluck('id')
            ->map(fn(int $id): string => self::CACHE_PREFIX . $id)
            ->all();

        return collect(cache()->many($cacheKeys))
            ->filter()
            ->map(fn(array $data): ?int => $data['alive_ip'] ?? null)
            ->filter()
            ->mapWithKeys(fn(int $count, string $key): array => [
                (int) Str::after($key, self::CACHE_PREFIX) => $count
            ])
            ->all();
    }

    /**
     * 获取指定用户的在线设备信息
     */
    public static function getUserDevices(int $userId): array
    {
        $data = cache()->get(self::CACHE_PREFIX . $userId, []);
        if (empty($data)) {
            return ['total_count' => 0, 'devices' => []];
        }

        $devices = collect($data)
            ->filter(fn(mixed $item): bool => is_array($item) && isset($item['aliveips']))
            ->flatMap(function (array $nodeData, string $nodeKey): array {
                return collect($nodeData['aliveips'])
                    ->mapWithKeys(function (string $ipNodeId) use ($nodeData, $nodeKey): array {
                        $ip = Str::before($ipNodeId, '_');
                        return [
                            $ip => [
                                'ip' => $ip,
                                'last_seen' => $nodeData['lastupdateAt'],
                                'node_type' => Str::before($nodeKey, (string) $nodeData['lastupdateAt'])
                            ]
                        ];
                    })
                    ->all();
            })
            ->values()
            ->all();

        return [
            'total_count' => $data['alive_ip'] ?? 0,
            'devices' => $devices
        ];
    }

    /**
     * 更新用户在线数据
     */
    public function updateAliveData(array $data, string $nodeType, int $nodeId): void
    {
        $updateAt = now()->timestamp;
        $nodeKey = $nodeType . $nodeId;
        $userUpdates = [];

        foreach ($data as $uid => $ips) {
            $cacheKey = self::CACHE_PREFIX . $uid;
            $ipsArray = cache()->get($cacheKey, []);
            $ipsArray = [
                ...collect($ipsArray)
                    ->filter(
                        fn(mixed $value): bool =>
                        is_array($value) &&
                        ($updateAt - ($value['lastupdateAt'] ?? 0) <= self::NODE_DATA_EXPIRY)
                    ),
                $nodeKey => [
                    'aliveips' => $ips,
                    'lastupdateAt' => $updateAt
                ]
            ];
            $count = $this->calculateDeviceCount($ipsArray);
            $ipsArray['alive_ip'] = $count;
            cache()->put($cacheKey, $ipsArray, now()->addSeconds(self::CACHE_TTL));

            $userUpdates[] = [
                'id' => $uid,
                'count' => $count,
            ];
        }

        // 使用队列异步更新数据库
        if (!empty($userUpdates)) {
            dispatch(new SyncUserOnlineStatusJob($userUpdates))
                ->onQueue('online_sync')
                ->afterCommit();
        }
    }

    /**
     * 批量获取用户在线设备数
     */
    public function getOnlineCounts(array $userIds): array
    {
        $cacheKeys = collect($userIds)
            ->map(fn(int $id): string => self::CACHE_PREFIX . $id)
            ->all();

        return collect(cache()->many($cacheKeys))
            ->filter()
            ->map(fn(array $data): int => $data['alive_ip'] ?? 0)
            ->all();
    }

    /**
     * 获取用户在线设备数
     */
    public function getOnlineCount(int $userId): int
    {
        $data = cache()->get(self::CACHE_PREFIX . $userId, []);
        return $data['alive_ip'] ?? 0;
    }

    /**
     * 清理过期的在线记录
     */
    public function cleanExpiredOnlineStatus(): void
    {
        dispatch(function () {
            User::query()
                ->where('last_online_at', '<', now()->subMinutes(5))
                ->update(['online_count' => 0]);
        })->onQueue('online_sync');
    }

    /**
     * Calculate the number of devices based on IPs array and device limit mode.
     *
     * @param array $ipsArray Array containing IP data
     * @return int Number of devices
     */
    private function calculateDeviceCount(array $ipsArray): int
    {
        $mode = (int) admin_setting('device_limit_mode', 0);

        return match ($mode) {
            // Loose mode: Count unique IPs (ignoring suffixes after '_')
            1 => collect($ipsArray)
                ->filter(fn(mixed $data): bool => is_array($data) && isset($data['aliveips']))
                ->flatMap(
                    fn(array $data): array => collect($data['aliveips'])
                        ->map(fn(string $ipNodeId): string => Str::before($ipNodeId, '_'))
                        ->unique()
                        ->all()
                )
                ->unique()
                ->count(),
            // Strict mode: Sum total number of alive IPs
            0 => collect($ipsArray)
                ->filter(fn(mixed $data): bool => is_array($data) && isset($data['aliveips']))
                ->sum(fn(array $data): int => count($data['aliveips'])),
            // Handle invalid modes
            default => throw new \InvalidArgumentException("Invalid device limit mode: $mode"),
        };
    }
}