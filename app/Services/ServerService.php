<?php

namespace App\Services;

use App\Models\ServerHysteria;
use App\Models\ServerLog;
use App\Models\ServerRoute;
use App\Models\ServerShadowsocks;
use App\Models\ServerVless;
use App\Models\User;
use App\Models\ServerVmess;
use App\Models\ServerTrojan;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ServerService
{
    // 获取可用的 VLESS 服务器列表
    public static function getAvailableVless(User $user): array
    {
        $servers = [];
        $model = ServerVless::orderBy('sort', 'ASC');
        $server = $model->get();
        foreach ($server as $key => $v) {
            if (!$v['show']) continue;
            $serverData = $v->toArray();

            $serverData['type'] = 'vless';
            if (!in_array($user->group_id, $serverData['group_id'])) continue;
            if (strpos($serverData['port'], '-') !== false) {
                $serverData['port'] = Helper::randomPort($serverData['port']);
            }
            if ($serverData['parent_id']) {
                $serverData['last_check_at'] = Cache::get(CacheKey::get('SERVER_VLESS_LAST_CHECK_AT', $serverData['parent_id']));
            } else {
                $serverData['last_check_at'] = Cache::get(CacheKey::get('SERVER_VLESS_LAST_CHECK_AT', $serverData['id']));
            }
            if (isset($serverData['tls_settings'])) {
                if (isset($serverData['tls_settings']['private_key'])) {
                    unset($serverData['tls_settings']['private_key']);
                }
            }

            $servers[] = $serverData;
        }

        return $servers;
    }

    // 获取可用的 VMESS 服务器列表
    public static function getAvailableVmess(User $user): array
    {
        $servers = [];
        $model = ServerVmess::orderBy('sort', 'ASC');
        $vmess = $model->get();
        foreach ($vmess as $key => $v) {
            if (!$v['show']) continue;
            $vmess[$key]['type'] = 'vmess';
            if (!in_array($user->group_id, $vmess[$key]['group_id'])) continue;
            if (strpos($vmess[$key]['port'], '-') !== false) {
                $vmess[$key]['port'] = Helper::randomPort($vmess[$key]['port']);
            }
            if ($vmess[$key]['parent_id']) {
                $vmess[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_VMESS_LAST_CHECK_AT', $vmess[$key]['parent_id']));
            } else {
                $vmess[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_VMESS_LAST_CHECK_AT', $vmess[$key]['id']));
            }
            $servers[] = $vmess[$key]->toArray();
        }

        return $servers;
    }

    // 获取可用的 TROJAN 服务器列表
    public static function getAvailableTrojan(User $user): array
    {
        $servers = [];
        $model = ServerTrojan::orderBy('sort', 'ASC');
        $trojan = $model->get();
        foreach ($trojan as $key => $v) {
            if (!$v['show']) continue;
            $trojan[$key]['type'] = 'trojan';
            if (!in_array($user->group_id, $trojan[$key]['group_id'])) continue;
            if (strpos($trojan[$key]['port'], '-') !== false) {
                $trojan[$key]['port'] = Helper::randomPort($trojan[$key]['port']);
            }
            if ($trojan[$key]['parent_id']) {
                $trojan[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_TROJAN_LAST_CHECK_AT', $trojan[$key]['parent_id']));
            } else {
                $trojan[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_TROJAN_LAST_CHECK_AT', $trojan[$key]['id']));
            }
            $servers[] = $trojan[$key]->toArray();
        }
        return $servers;
    }

    // 获取可用的 HYSTERIA 服务器列表
    public static function getAvailableHysteria(User $user)
    {
        $availableServers = [];
        $model = ServerHysteria::orderBy('sort', 'ASC');
        $servers = $model->get()->keyBy('id');
        foreach ($servers as $key => $v) {
            if (!$v['show']) continue;
            $servers[$key]['type'] = 'hysteria';
            $servers[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_HYSTERIA_LAST_CHECK_AT', $v['id']));
            if (!in_array($user->group_id, $v['group_id'])) continue;
            if (strpos($v['port'], '-') !== false) {
                $servers[$key]['ports'] = $v['port'];
                $servers[$key]['port'] = Helper::randomPort($v['port']);
            }
            if (isset($servers[$v['parent_id']])) {
                $servers[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_HYSTERIA_LAST_CHECK_AT', $v['parent_id']));
                $servers[$key]['created_at'] = $servers[$v['parent_id']]['created_at'];
            }
            $servers[$key]['server_key'] = Helper::getServerKey($servers[$key]['created_at'], 16);
            $availableServers[] = $servers[$key]->toArray();
        }
        return $availableServers;
    }

    // 获取可用的 SHADOWSOCKS 服务器列表
    public static function getAvailableShadowsocks(User $user)
    {
        $servers = [];
        $model = ServerShadowsocks::orderBy('sort', 'ASC');
        $shadowsocks = $model->get()->keyBy('id');
        foreach ($shadowsocks as $key => $v) {
            if (!$v['show']) continue;
            $shadowsocks[$key]['type'] = 'shadowsocks';
            $shadowsocks[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_SHADOWSOCKS_LAST_CHECK_AT', $v['id']));
            if (!in_array($user->group_id, $v['group_id'])) continue;
            if (strpos($v['port'], '-') !== false) {
                $shadowsocks[$key]['port'] = Helper::randomPort($v['port']);
            }
            if (isset($shadowsocks[$v['parent_id']])) {
                $shadowsocks[$key]['last_check_at'] = Cache::get(CacheKey::get('SERVER_SHADOWSOCKS_LAST_CHECK_AT', $v['parent_id']));
                $shadowsocks[$key]['created_at'] = $shadowsocks[$v['parent_id']]['created_at'];
            }
            // 处理ss2022密码
            $cipherConfiguration = [
                '2022-blake3-aes-128-gcm' => [
                    'serverKeySize' => 16,
                    'userKeySize' => 16,
                ],
                '2022-blake3-aes-256-gcm' => [
                    'serverKeySize' => 32,
                    'userKeySize' => 32,
                ],
                '2022-blake3-chacha20-poly1305' => [
                    'serverKeySize' => 32,
                    'userKeySize' => 32,
                ]
            ];
            $shadowsocks[$key]['password'] = $user['uuid'];
            if (array_key_exists($cipher = $v['cipher'], $cipherConfiguration)) {
                $config = $cipherConfiguration[$cipher];
                $serverKey = Helper::getServerKey($v['created_at'], $config['serverKeySize']);
                $userKey = Helper::uuidToBase64($user['uuid'], $config['userKeySize']);
                $shadowsocks[$key]['password'] = "{$serverKey}:{$userKey}";
            }
            $servers[] = $shadowsocks[$key]->toArray();
        }
        return $servers;
    }

    // 获取可用的服务器列表
    public static function getAvailableServers(User $user)
    {
        $servers = Cache::remember('serversAvailable_'. $user->id, 5, function() use($user){
            return array_merge(
                self::getAvailableShadowsocks($user),
                self::getAvailableVmess($user),
                self::getAvailableTrojan($user),
                self::getAvailableHysteria($user),
                self::getAvailableVless($user)
            );
        });
        $tmp = array_column($servers, 'sort');
        array_multisort($tmp, SORT_ASC, $servers);
        return array_map(function ($server) {
            $server['port'] = (int)$server['port'];
            $server['is_online'] = (time() - 300 > $server['last_check_at']) ? 0 : 1;
            $server['cache_key'] = "{$server['type']}-{$server['id']}-{$server['updated_at']}-{$server['is_online']}";
            return $server;
        }, $servers);
    }

    // 获取可用的用户列表
    public static function getAvailableUsers($groupId): Collection
    {
        return \DB::table('v2_user')
            ->whereIn('group_id', $groupId)
            ->whereRaw('u + d < transfer_enable')
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                    ->orWhere('expired_at', NULL);
            })
            ->where('banned', 0)
            ->select([
                'id',
                'uuid',
                'speed_limit'
            ])
            ->get();
    }

    // 记录流量日志
    public static function log(int $userId, int $serverId, int $u, int $d, float $rate, string $method)
    {
        if (($u + $d) < 10240) return true;
        $timestamp = strtotime(date('Y-m-d'));
        $serverLog = ServerLog::where('log_at', '>=', $timestamp)
            ->where('log_at', '<', $timestamp + 3600)
            ->where('server_id', $serverId)
            ->where('user_id', $userId)
            ->where('rate', $rate)
            ->where('method', $method)
            ->first();
        if ($serverLog) {
            try {
                $serverLog->increment('u', $u);
                $serverLog->increment('d', $d);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            $serverLog = new ServerLog();
            $serverLog->user_id = $userId;
            $serverLog->server_id = $serverId;
            $serverLog->u = $u;
            $serverLog->d = $d;
            $serverLog->rate = $rate;
            $serverLog->log_at = $timestamp;
            $serverLog->method = $method;
            return $serverLog->save();
        }
    }

    // 获取所有 SHADOWSOCKS 服务器列表
    public static function getAllShadowsocks()
    {
        $servers = ServerShadowsocks::orderBy('sort', 'ASC')
            ->get()
            ->toArray();
        foreach ($servers as $k => $v) {
            $servers[$k]['type'] = 'shadowsocks';
        }
        return $servers;
    }

    // 获取所有 VMESS 服务器列表
    public static function getAllVMess()
    {
        $servers = ServerVmess::orderBy('sort', 'ASC')
            ->get()
            ->toArray();
        foreach ($servers as $k => $v) {
            $servers[$k]['type'] = 'vmess';
        }
        return $servers;
    }

    // 获取所有 VLESS 服务器列表
    public static function getAllVLess()
    {
        $servers = ServerVless::orderBy('sort', 'ASC')
            ->get()
            ->toArray();
        foreach ($servers as $k => $v) {
            $servers[$k]['type'] = 'vless';
        }
        return $servers;
    }

    // 获取所有 TROJAN 服务器列表
    public static function getAllTrojan()
    {
        $servers = ServerTrojan::orderBy('sort', 'ASC')
            ->get()
            ->toArray();
        foreach ($servers as $k => $v) {
            $servers[$k]['type'] = 'trojan';
        }
        return $servers;
    }

    // 获取所有 HYSTERIA 服务器列表
    public static function getAllHysteria()
    {
        $servers = ServerHysteria::orderBy('sort', 'ASC')
            ->get()
            ->toArray();
        foreach ($servers as $k => $v) {
            $servers[$k]['type'] = 'hysteria';
        }
        return $servers;
    }

    // 合并数据
    private static function mergeData(&$servers)
    {
        foreach ($servers as $k => $v) {
            $serverType = strtoupper($v['type']);

            $servers[$k]['online'] = Cache::get(CacheKey::get("SERVER_{$serverType}_ONLINE_USER", $v['parent_id'] ?? $v['id'])) ?? 0;
            // 如果是子节点，先尝试从缓存中获取
            if($pid = $v['parent_id']){
                // 获取缓存
                $onlineUsers = Cache::get(CacheKey::get('MULTI_SERVER_' . $serverType . '_ONLINE_USER', $pid)) ?? [];
                $servers[$k]['online'] = (collect($onlineUsers)->whereIn('ip', $v['ips'])->sum('online_user')) . "|{$servers[$k]['online']}";
            }
            $servers[$k]['last_check_at'] = Cache::get(CacheKey::get("SERVER_{$serverType}_LAST_CHECK_AT", $v['parent_id'] ?? $v['id']));
            $servers[$k]['last_push_at'] = Cache::get(CacheKey::get("SERVER_{$serverType}_LAST_PUSH_AT", $v['parent_id'] ?? $v['id']));
            if ((time() - 300) >= $servers[$k]['last_check_at']) {
                $servers[$k]['available_status'] = 0;
            } else if ((time() - 300) >= $servers[$k]['last_push_at']) {
                $servers[$k]['available_status'] = 1;
            } else {
                $servers[$k]['available_status'] = 2;
            }
        }
    }

    // 获取所有服务器列表
    public static function getAllServers()
    {
        $servers = array_merge(
            self::getAllShadowsocks(),
            self::getAllVMess(),
            self::getAllTrojan(),
            self::getAllHysteria(),
            self::getAllVLess()
        );
        self::mergeData($servers);
        $tmp = array_column($servers, 'sort');
        array_multisort($tmp, SORT_ASC, $servers);
        return $servers;
    }

    // 获取路由规则
    public static function getRoutes(array $routeIds)
    {
        $routes = ServerRoute::select(['id', 'match', 'action', 'action_value'])->whereIn('id', $routeIds)->get();
        // TODO: remove on 1.8.0
        foreach ($routes as $k => $route) {
            $array = json_decode($route->match, true);
            if (is_array($array)) $routes[$k]['match'] = $array;
        }
        // TODO: remove on 1.8.0
        return $routes;
    }

    // 获取服务器
    public static function getServer($serverId, $serverType)
    {
        switch ($serverType) {
            case 'vmess':
                return ServerVmess::find($serverId);
            case 'shadowsocks':
                return ServerShadowsocks::find($serverId);
            case 'trojan':
                return ServerTrojan::find($serverId);
            case 'hysteria':
                return ServerHysteria::find($serverId);
            case 'vless':
                return ServerVless::find($serverId);
            default:
                return false;
        }
    }

    // 根据节点IP和父级别节点ID查询子节点
    public static function getChildServer($serverId, $serverType, $nodeIp){
        switch ($serverType) {
            case 'vmess':
                return ServerVmess::query()
                        ->where("parent_id", $serverId)
                        ->where('ips',"like", "%\"$nodeIp\"%")
                        ->first();
            case 'shadowsocks':
                return ServerShadowsocks::query()
                        ->where("parent_id", $serverId)
                        ->where('ips',"like", "%\"$nodeIp\"%")
                        ->first();
            case 'trojan':
                return ServerTrojan::query()
                        ->where("parent_id", $serverId)
                        ->where('ips',"like", "%\"$nodeIp\"%")
                        ->first();
            case 'hysteria':
                return ServerHysteria::query()
                        ->where("parent_id", $serverId)
                        ->where('ips',"like", "%\"$nodeIp\"%")
                        ->first();
            case 'vless':
                return ServerVless::query()
                        ->where("parent_id", $serverId)
                        ->where('ips',"like", "%\"$nodeIp\"%")
                        ->first();
            default:
                return null;
        }
    }
}
