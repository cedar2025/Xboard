<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UniProxyController extends Controller
{
    private $nodeType;
    private $nodeInfo;
    private $nodeId;
    private $serverService;

    public function __construct(ServerService $serverService, Request $request)
    {
        $this->serverService = $serverService;
        $this->nodeId = $request->input('node_id');
        $this->nodeType = $request->input('node_type');
        $this->nodeInfo = $this->serverService->getServer($this->nodeId, $this->nodeType);
        if(!$this->nodeInfo) {
            Log::channel("daily")->info("$this->nodeId  $this->nodeType  $this->nodeInfo");
            throw new \Exception('server is not exist', 500);
        };
    }

    // 后端获取用户
    public function user(Request $request)
    {
        ini_set('memory_limit', -1);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_LAST_CHECK_AT', $this->nodeInfo->id), time(), 3600);
        $users = $this->serverService->getAvailableUsers($this->nodeInfo->group_id);
        $users = $users->toArray();

        $response['users'] = $users;

        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match'), $eTag) !== false ) {
            return response(null, 304);
        };

        return response($response)->header('ETag', "\"{$eTag}\"");
    }

    // 后端提交数据
    public function push(Request $request)
    {
        $data = get_request_content();
        $data = json_decode($data, true);

        // 增加单节点多服务器统计在线人数
        $ip = $request->ip();
        $id = $request->input("id");
        $time = time();
        $cacheKey =  CacheKey::get('MULTI_SERVER_' . strtoupper($this->nodeType) . '_ONLINE_USER', $this->nodeInfo->id);

        // 1、获取节点节点在线人数缓存
        $onlineUsers = Cache::get($cacheKey) ?? [];
        $onlineCollection = collect($onlineUsers);
        // 过滤掉超过600秒的记录
        $onlineCollection = $onlineCollection->reject(function ($item) use ($time) {
            return $item['time'] < ($time - 600);
        });
        // 定义数据
        $updatedItem = [
            'id' => $id ?? $ip,
            'ip' => $ip,
            'online_user' => count($data),
            'time' => $time
        ];

        $existingItemIndex = $onlineCollection->search(function ($item) use ($updatedItem) {
            return ($item['id'] ?? '') === $updatedItem['id'];
        });
        if ($existingItemIndex !== false) {
            $onlineCollection[$existingItemIndex] = $updatedItem;
        } else {
            $onlineCollection->push($updatedItem);
        }
        $onlineUsers = $onlineCollection->all();
        Cache::put($cacheKey, $onlineUsers, 3600);

        $online_user = $onlineCollection->sum('online_user');
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_ONLINE_USER', $this->nodeInfo->id), $online_user, 3600);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_LAST_PUSH_AT', $this->nodeInfo->id), time(), 3600);
        $userService = new UserService();
        $userService->trafficFetch($this->nodeInfo->toArray(), $this->nodeType, $data , $ip);

        return $this->success(true);
    }

    // 后端获取配置
    public function config(Request $request)
    {
        switch ($this->nodeType) {
            case 'shadowsocks':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'cipher' => $this->nodeInfo->cipher,
                    'obfs' => $this->nodeInfo->obfs,
                    'obfs_settings' => $this->nodeInfo->obfs_settings
                ];

                if ($this->nodeInfo->cipher === '2022-blake3-aes-128-gcm') {
                    $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 16);
                }
                if ($this->nodeInfo->cipher === '2022-blake3-aes-256-gcm') {
                    $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 32);
                }
                break;
            case 'vmess':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'network' => $this->nodeInfo->network,
                    'networkSettings' => $this->nodeInfo->networkSettings,
                    'tls' => $this->nodeInfo->tls
                ];
                break;
            case 'trojan':
                $response = [
                    'host' => $this->nodeInfo->host,
                    'server_port' => $this->nodeInfo->server_port,
                    'server_name' => $this->nodeInfo->server_name,
                    'network' => $this->nodeInfo->network,
                    'networkSettings' => $this->nodeInfo->networkSettings,
                ];
                break;
            case 'hysteria':
                $response = [
                    'version' => $this->nodeInfo->version,
                    'host' => $this->nodeInfo->host,
                    'server_port' => $this->nodeInfo->server_port,
                    'server_name' => $this->nodeInfo->server_name,
                    'up_mbps' => $this->nodeInfo->up_mbps,
                    'down_mbps' => $this->nodeInfo->down_mbps,
                    'obfs' => $this->nodeInfo->is_obfs ? Helper::getServerKey($this->nodeInfo->created_at, 16) : null
                ];
                break;
            case "vless":
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'network' => $this->nodeInfo->network,
                    'network_settings' => $this->nodeInfo->network_settings,
                    'networkSettings' => $this->nodeInfo->network_settings,
                    'tls' => $this->nodeInfo->tls,
                    'flow' => $this->nodeInfo->flow,
                    'tls_settings' => $this->nodeInfo->tls_settings
                ];
                break;
        }
        $response['base_config'] = [
            'push_interval' => (int)admin_setting('server_push_interval', 60),
            'pull_interval' => (int)admin_setting('server_pull_interval', 60)
        ];
        if ($this->nodeInfo['route_id']) {
            $response['routes'] = $this->serverService->getRoutes($this->nodeInfo['route_id']);
        }
        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match'), $eTag) !== false ) {
            return response(null,304);
        }

        return response($response)->header('ETag', "\"{$eTag}\"");
    }

     // 后端提交在线数据
     public function alive(Request $request)
     {
        return $this->success(true);
     }
}
