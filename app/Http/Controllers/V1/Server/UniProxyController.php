<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class UniProxyController extends Controller
{
    // 后端获取用户
    public function user(Request $request)
    {
        ini_set('memory_limit', -1);
        $node = $request->input('node_info');
        $nodeType = $node->type;
        $nodeId = $node->id;
        Cache::put(CacheKey::get('SERVER_' . strtoupper($nodeType) . '_LAST_CHECK_AT', $nodeId), time(), 3600);
        $users = ServerService::getAvailableUsers($node->group_ids);

        $response['users'] = $users;

        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match'), $eTag) !== false) {
            return response(null, 304);
        }

        return response($response)->header('ETag', "\"{$eTag}\"");
    }

    // 后端提交数据
    public function push(Request $request)
    {
        $res = json_decode(get_request_content(), true);
        $data = array_filter($res, function ($item) {
            return is_array($item) && count($item) === 2 && is_numeric($item[0]) && is_numeric($item[1]);
        });
        $node = $request->input('node_info');
        $nodeType = $node->type;
        $nodeId = $node->id;
        Cache::put(CacheKey::get('SERVER_' . strtoupper($nodeType) . '_ONLINE_USER', $nodeId), count($data), 3600);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($nodeType) . '_LAST_PUSH_AT', $nodeId), time(), 3600);
        $userService = new UserService();
        $userService->trafficFetch($node->toArray(), $nodeType, $data);
        return $this->success(true);
    }

    // 后端获取配置
    public function config(Request $request)
    {
        $node = $request->input('node_info');
        $nodeType = $node->type;

        $protocolSettings = $node->protocol_settings;
        $response = match ($nodeType) {
            'shadowsocks' => [
                'server_port' => $node->server_port,
                'cipher' => $protocolSettings['cipher'],
                'obfs' => $protocolSettings['obfs'],
                'obfs_settings' => $protocolSettings['obfs_settings'],
                'server_key' => $protocolSettings['cipher'] === '2022-blake3-aes-128-gcm'
                    ? Helper::getServerKey($node->created_at, 16)
                    : ($protocolSettings['cipher'] === '2022-blake3-aes-256-gcm'
                        ? Helper::getServerKey($node->created_at, 32)
                        : null)
            ],
            'vmess' => [
                'server_port' => $node->server_port,
                'network' => $protocolSettings['network'],
                'networkSettings' => $protocolSettings['network_settings'],
                'tls' => $protocolSettings['tls']
            ],
            'trojan' => [
                'host' => $node->host,
                'server_port' => $node->server_port,
                'server_name' => $protocolSettings['server_name'],
                'network' => $protocolSettings['network'],
                'networkSettings' => $protocolSettings['network_settings'],
            ],
            'vless' => [
                'server_port' => $node->server_port,
                'network' => $protocolSettings['network'],
                'network_settings' => $protocolSettings['network_settings'],
                'tls' => $protocolSettings['tls'],
                'flow' => $protocolSettings['flow'],
                'tls_settings' => $protocolSettings['tls_settings']
            ],
            'hysteria' => [
                'version' => $protocolSettings['version'],
                'host' => $node->host,
                'server_port' => $node->server_port,
                'server_name' => $protocolSettings['tls']['server_name'],
                'up_mbps' => $protocolSettings['bandwidth']['up'],
                'down_mbps' => $protocolSettings['bandwidth']['down'],
                'obfs' => $protocolSettings['obfs']['open'] ? $protocolSettings['obfs']['password'] : null
            ],
            default => []
        };
        $response['base_config'] = [
            'push_interval' => (int) admin_setting('server_push_interval', 60),
            'pull_interval' => (int) admin_setting('server_pull_interval', 60)
        ];
        if ($node['route_id']) {
            $response['routes'] = ServerService::getRoutes($node['route_id']);
        }
        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match'), $eTag) !== false) {
            return response(null, 304);
        }

        return response($response)->header('ETag', "\"{$eTag}\"");
    }

    // 后端提交在线数据
    public function alive(Request $request)
    {
        return $this->success(true);
    }
}
