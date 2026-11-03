<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class DeviceStateService
{
    private const PREFIX = 'user_devices:';
    private const TTL = 300;                     // device state ttl
    private const DB_THROTTLE = 10;             // update db throttle

    /**
     * 移除 Redis key 的前缀
     */
    private function removeRedisPrefix(string $key): string
    {
        $prefix = config('database.redis.options.prefix', '');
        return $prefix ? substr($key, strlen($prefix)) : $key;
    }

    /**
     * 批量设置设备
     * 用于 HTTP /alive 和 WebSocket report.devices
     */
    public function setDevices(int $userId, int $nodeId, array $ips): void
    {
        $key = self::PREFIX . $userId;
        $timestamp = time();

        $this->removeNodeDevices($nodeId, $userId);

        // Normalize: strip port suffix and deduplicate
        $ips = array_values(array_unique(array_map([self::class, 'normalizeIP'], $ips)));

        if (!empty($ips)) {
            $fields = [];
            foreach ($ips as $ip) {
                $fields["{$nodeId}:{$ip}"] = $timestamp;
            }
            Redis::hMset($key, $fields);
            Redis::expire($key, self::TTL);
        }

        $this->notifyUpdate($userId);
    }

    /**
     * 获取某节点的所有设备数据
     * 返回: {userId: [ip1, ip2, ...], ...}
     */
    public function getNodeDevices(int $nodeId): array
    {
        return $this->getNodesDevices([$nodeId])[$nodeId];
    }

    /**
     * 一次扫描获取多个节点的设备数据
     * 返回: {nodeId: {userId: [ip1, ip2, ...], ...}, ...}
     */
    public function getNodesDevices(array $nodeIds): array
    {
        $result = array_fill_keys($nodeIds, []);
        if (empty($nodeIds)) {
            return $result;
        }

        $keys = array_map(fn($key) => $this->removeRedisPrefix($key), Redis::keys(self::PREFIX . '*'));
        if (empty($keys)) {
            return $result;
        }

        // 一次往返取回所有哈希，避免每个在线用户一次请求
        $hashes = Redis::pipeline(function ($pipe) use ($keys) {
            foreach ($keys as $key) {
                $pipe->hgetall($key);
            }
        });

        foreach ($keys as $index => $key) {
            $uid = (int) substr($key, strlen(self::PREFIX));
            foreach ($hashes[$index] ?: [] as $field => $timestamp) {
                $separator = strpos($field, ':');
                if ($separator === false) {
                    continue;
                }
                $nodeId = (int) substr($field, 0, $separator);
                if (isset($result[$nodeId])) {
                    $result[$nodeId][$uid][] = substr($field, $separator + 1);
                }
            }
        }

        return $result;
    }

    /**
     * 删除某节点某用户的设备
     */
    public function removeNodeDevices(int $nodeId, int $userId): void
    {
        $key = self::PREFIX . $userId;
        $prefix = "{$nodeId}:";

        foreach (Redis::hkeys($key) as $field) {
            if (str_starts_with($field, $prefix)) {
                Redis::hdel($key, $field);
            }
        }
    }

    /**
     * 清除节点所有设备数据（用于节点断开连接）
     */
    public function clearAllNodeDevices(int $nodeId): array
    {
        return $this->clearNodesDevices([$nodeId]);
    }

    /**
     * 一次扫描清除多个节点的设备数据，受影响用户合并通知
     * 返回受影响的用户 ID
     */
    public function clearNodesDevices(array $nodeIds): array
    {
        $affected = [];

        foreach ($this->getNodesDevices($nodeIds) as $nodeId => $devices) {
            foreach ($devices as $userId => $ips) {
                $fields = array_map(fn($ip) => "{$nodeId}:{$ip}", $ips);
                Redis::hdel(self::PREFIX . $userId, ...$fields);
                $affected[$userId] = true;
            }
        }

        $this->notifyUpdates(array_keys($affected));

        return array_keys($affected);
    }

    /**
     * get user device count (deduplicated by IP, filter expired data)
     */
    public function getDeviceCount(int $userId): int
    {
        $data = Redis::hgetall(self::PREFIX . $userId);
        $now = time();
        $ips = [];

        foreach ($data as $field => $timestamp) {
            if ($now - $timestamp <= self::TTL) {
                $ips[] = substr($field, strpos($field, ':') + 1);
            }
        }

        return count(array_unique($ips));
    }

    /**
     * get user device count (for alivelist interface)
     */
    public function getAliveList(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $result = [];
        foreach ($users as $user) {
            $count = $this->getDeviceCount($user->id);
            if ($count > 0) {
                $result[$user->id] = $count;
            }
        }

        return $result;
    }

    /**
     * get devices of multiple users (for sync.devices, filter expired data)
     */
    public function getUsersDevices(array $userIds): array
    {
        $result = [];
        $now = time();
        foreach ($userIds as $userId) {
            $data = Redis::hgetall(self::PREFIX . $userId);
            if (!empty($data)) {
                $ips = [];
                foreach ($data as $field => $timestamp) {
                    if ($now - $timestamp <= self::TTL) {
                        $ips[] = substr($field, strpos($field, ':') + 1);
                    }
                }
                if (!empty($ips)) {
                    $result[$userId] = array_values(array_unique($ips));
                }
            }
        }

        return $result;
    }

    /**
     * Strip port from IP address: "1.2.3.4:12345" → "1.2.3.4", "[::1]:443" → "::1"
     */
    private static function normalizeIP(string $ip): string
    {
        // [IPv6]:port
        if (preg_match('/^\[(.+)\]:\d+$/', $ip, $m)) {
            return $m[1];
        }
        // IPv4:port
        if (preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $ip, $m)) {
            return $m[1];
        }
        return $ip;
    }

    /**
     * notify update (throttle control)
     */
    public function notifyUpdate(int $userId): void
    {
        $dbThrottleKey = "device:db_throttle:{$userId}";

        // if (Redis::setnx($dbThrottleKey, 1)) {
        //     Redis::expire($dbThrottleKey, self::DB_THROTTLE);

            User::query()
                ->whereKey($userId)
                ->update([
                    'online_count' => $this->getDeviceCount($userId),
                    'last_online_at' => now(),
                ]);
        // }
    }

    /**
     * notify update for many users: one statement per distinct device count
     * instead of one per user
     */
    public function notifyUpdates(array $userIds): void
    {
        $userIdsByCount = [];
        foreach ($userIds as $userId) {
            $userIdsByCount[$this->getDeviceCount($userId)][] = $userId;
        }

        foreach ($userIdsByCount as $count => $ids) {
            User::query()
                ->whereIn('id', $ids)
                ->update([
                    'online_count' => $count,
                    'last_online_at' => now(),
                ]);
        }
    }
}
