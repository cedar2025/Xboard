<?php


namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserOnlineService
{
    /**
     * 缓存相关常量
     */
    private const CACHE_PREFIX = 'ALIVE_IP_USER_';

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
     * 计算在线设备数量
     */
    public static function calculateDeviceCount(array $ipsArray): int
    {
        $mode = (int) admin_setting('device_limit_mode', 0);

        return match ($mode) {
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
            0 => collect($ipsArray)
                ->filter(fn(mixed $data): bool => is_array($data) && isset($data['aliveips']))
                ->sum(fn(array $data): int => count($data['aliveips'])),
            default => throw new \InvalidArgumentException("Invalid device limit mode: $mode"),
        };
    }
}