<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerRoute;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Collection;

class ServerService
{

    /**
     * 获取所有服务器列表
     * @return Collection
     */
    public static function getAllServers()
    {
        return Server::orderBy('sort', 'ASC')
            ->get()
            ->transform(function (Server $server) {
                $server->loadServerStatus();
                return $server;
            });
    }

    /**
     * 获取指定用户可用的服务器列表
     * @param User $user
     * @return array
     */
    public static function getAvailableServers(User $user): array
    {
        return Server::whereJsonContains('group_ids', (string) $user->group_id)
            ->where('show', true)
            ->orderBy('sort', 'ASC')
            ->get()
            ->transform(function (Server $server) use ($user) {
                $server->loadParentCreatedAt();
                $server->handlePortAllocation();
                $server->loadServerStatus();
                if ($server->type === 'shadowsocks') {
                    $server->server_key = Helper::getServerKey($server->created_at, 16);
                }
                $server->generateShadowsocksPassword($user);

                return $server;
            })
            ->toArray();
    }

    /** 
     * 加
     */

    /**
     * 根据权限组获取可用的用户列表
     * @param array $groupIds
     * @return Collection
     */
    public static function getAvailableUsers(array $groupIds)
    {
        return User::toBase()
            ->whereIn('group_id', $groupIds)
            ->whereRaw('u + d < transfer_enable')
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                    ->orWhere('expired_at', NULL);
            })
            ->where('banned', 0)
            ->select([
                'id',
                'uuid',
                'speed_limit',
                'device_limit'
            ])
            ->get();
    }

    // 获取路由规则
    public static function getRoutes(array $routeIds)
    {
        $routes = ServerRoute::select(['id', 'match', 'action', 'action_value'])->whereIn('id', $routeIds)->get();
        // TODO: remove on 1.8.0
        foreach ($routes as $k => $route) {
            $array = json_decode($route->match, true);
            if (is_array($array))
                $routes[$k]['match'] = $array;
        }
        // TODO: remove on 1.8.0
        return $routes;
    }

    /**
     * 根据协议类型和标识获取服务器
     * @param int $serverId
     * @param string $serverType
     * @return Server|null
     */
    public static function getServer($serverId, $serverType)
    {
        return Server::query()
            ->where('type', Server::normalizeType($serverType))
            ->where(function ($query) use ($serverId) {
                $query->where('code', $serverId)
                    ->orWhere('id', $serverId);
            })
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$serverId])
            ->first();
    }
}
