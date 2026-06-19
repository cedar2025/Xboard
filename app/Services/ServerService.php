<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerRoute;
use App\Models\User;
use App\Models\UserTrafficPackage;
use App\Services\Plugin\HookManager;
use App\Utils\Helper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ServerService
{

    /**
     * 获取所有服务器列表
     * @return Collection
     */
    public static function getAllServers(): Collection
    {
        return self::sortByDisplayNodeId(Server::query()->get())->append([
            'last_check_at',
            'last_push_at',
            'online',
            'is_online',
            'available_status',
            'cache_key',
            'load_status'
        ]);
    }

    /**
     * 获取指定用户可用的服务器列表
     * @param User $user
     * @return array
     */
    public static function getAvailableServers(User $user): array
    {
        $servers = Server::whereJsonContains('group_ids', (string) $user->group_id)
            ->where('show', true)
            ->get();

        $servers = self::sortByDisplayNodeId($servers)
            ->append(['last_check_at', 'last_push_at', 'online', 'is_online', 'available_status', 'cache_key', 'server_key']);

        $servers = collect($servers)->map(function ($server) use ($user) {
            // 判断动态端口
            if (str_contains($server->port, '-')) {
                $port = $server->port;
                $server->port = (int) Helper::randomPort($port);
                $server->ports = $port;
            } else {
                $server->port = (int) $server->port;
            }
            $server->password = $server->generateServerPassword($user);
            return $server;
        })->toArray();

        return $servers;
    }

    private static function sortByDisplayNodeId(Collection $servers): Collection
    {
        return $servers->sort(function (Server $left, Server $right): int {
            $leftDisplayId = (string) ($left->code ?: $left->id);
            $rightDisplayId = (string) ($right->code ?: $right->id);
            $displayIdComparison = strnatcasecmp($leftDisplayId, $rightDisplayId);

            return $displayIdComparison !== 0
                ? $displayIdComparison
                : $left->id <=> $right->id;
        })->values();
    }

    /**
     * 根据权限组获取可用的用户列表
     * @param array $groupIds
     * @return Collection
     */
    public static function getAvailableUsers(Server $node)
    {
        $users = User::query()
            ->leftJoin('v2_user_traffic_packages as traffic_packages', function ($join) {
                $join->on('v2_user.id', '=', 'traffic_packages.user_id')
                    ->where('traffic_packages.status', UserTrafficPackage::STATUS_ACTIVE)
                    ->where('traffic_packages.remaining_bytes', '>', 0);
            })
            ->whereIn('v2_user.group_id', $node->group_ids)
            ->where('v2_user.banned', 0)
            ->select([
                'v2_user.id',
                'v2_user.uuid',
                'v2_user.speed_limit',
                'v2_user.device_limit',
                DB::raw('COALESCE(SUM(traffic_packages.remaining_bytes), 0) as package_remaining'),
            ])
            ->groupBy([
                'v2_user.id',
                'v2_user.uuid',
                'v2_user.speed_limit',
                'v2_user.device_limit',
                'v2_user.expired_at',
                'v2_user.u',
                'v2_user.d',
                'v2_user.transfer_enable',
            ])
            ->havingRaw('(v2_user.expired_at >= ? OR v2_user.expired_at IS NULL) AND (v2_user.u + v2_user.d < v2_user.transfer_enable)', [time()])
            ->orHavingRaw('COALESCE(SUM(traffic_packages.remaining_bytes), 0) > 0')
            ->get();
        return HookManager::filter('server.users.get', $users, $node);
    }

    // 获取路由规则
    public static function getRoutes(array $routeIds)
    {
        $routes = ServerRoute::select(['id', 'match', 'action', 'action_value'])->whereIn('id', $routeIds)->get();
        return $routes;
    }

    /**
     * 根据协议类型和标识获取服务器
     * @param int $serverId
     * @param string $serverType
     * @return Server|null
     */
    public static function getServer($serverId, ?string $serverType)
    {
        return Server::query()
            ->when($serverType, function ($query) use ($serverType) {
                $query->where('type', Server::normalizeType($serverType));
            })
            ->where(function ($query) use ($serverId) {
                $query->where('code', $serverId)
                    ->orWhere('id', $serverId);
            })
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$serverId])
            ->first();
    }
}
