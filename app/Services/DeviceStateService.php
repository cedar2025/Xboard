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
     * 批量设置设备
     * 用于 HTTP /alive 和 WebSocket report.devices
     */
    public function setDevices(int $userId, int $nodeId, array $ips): void
    {
        $key = self::PREFIX . $userId;
        $timestamp = time();

        $this->clearNodeDevices($nodeId, $userId);

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
     * clear node devices
     * - only nodeId: clear all devices of the node
     * - userId and nodeId: clear specific user's specific node device
     */
    public function clearNodeDevices(int $nodeId, ?int $userId = null): void
    {
        if ($userId !== null) {
            $key = self::PREFIX . $userId;
            $prefix = "{$nodeId}:";
            foreach (Redis::hkeys($key) as $field) {
                if (str_starts_with($field, $prefix)) {
                    Redis::hdel($key, $field);
                }
            }
            return;
        }

        $keys = Redis::keys(self::PREFIX . '*');
        $prefix = "{$nodeId}:";

        foreach ($keys as $key) {
            foreach (Redis::hkeys($key) as $field) {
                if (str_starts_with($field, $prefix)) {
                    Redis::hdel($key, $field);
                }
            }
        }
    }

    /**
     * get user device count (filter expired data)
     */
    public function getDeviceCount(int $userId): int
    {
        $data = Redis::hgetall(self::PREFIX . $userId);
        $now = time();
        $count = 0;
        foreach ($data as $field => $timestamp) {
            // if ($now - $timestamp <= self::TTL) {
                $count++;
            // }
        }
        return $count;
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
                    // if ($now - $timestamp <= self::TTL) {
                        $ips[] = substr($field, strpos($field, ':') + 1);
                    // }
                }
                if (!empty($ips)) {
                    $result[$userId] = array_unique($ips);
                }
            }
        }

        return $result;
    }

    /**
     * notify update (throttle control)
     */
    private function notifyUpdate(int $userId): void
    {
        $dbThrottleKey = "device:db_throttle:{$userId}";

        if (Redis::setnx($dbThrottleKey, 1)) {
            Redis::expire($dbThrottleKey, self::DB_THROTTLE);

            User::query()
                ->whereKey($userId)
                ->update([
                    'online_count' => $this->getDeviceCount($userId),
                    'last_online_at' => now(),
                ]);
        }
    }
}
