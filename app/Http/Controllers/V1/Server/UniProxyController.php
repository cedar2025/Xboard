<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Jobs\UpdateAliveDataJob;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\UserOnlineService;
use Illuminate\Http\JsonResponse;

class UniProxyController extends Controller
{
    public function __construct(
        private readonly UserOnlineService $userOnlineService
    ) {
    }

    /**
     * 获取当前请求的节点信息
     */
    private function getNodeInfo(Request $request)
    {
        return $request->attributes->get('node_info');
    }

    // 后端获取用户
    public function user(Request $request)
    {
        ini_set('memory_limit', -1);
        $node = $this->getNodeInfo($request);
        $nodeType = $node->type;
        $nodeId = $node->id;
        Cache::put(CacheKey::get('SERVER_' . strtoupper($nodeType) . '_LAST_CHECK_AT', $nodeId), time(), 3600);
        $users = ServerService::getAvailableUsers($node);

        $response['users'] = $users;

        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match', ''), $eTag) !== false) {
            return response(null, 304);
        }

        return response($response)->header('ETag', "\"{$eTag}\"");
    }

    // 后端提交数据
    public function push(Request $request)
    {
        $res = json_decode(request()->getContent(), true);
        if (!is_array($res)) {
            return $this->fail([422, 'Invalid data format']);
        }
        $data = array_filter($res, function ($item) {
            return is_array($item)
                && count($item) === 2
                && is_numeric($item[0])
                && is_numeric($item[1]);
        });
        if (empty($data)) {
            return $this->success(true);
        }
        $node = $this->getNodeInfo($request);
        $nodeType = $node->type;
        $nodeId = $node->id;

        Cache::put(
            CacheKey::get('SERVER_' . strtoupper($nodeType) . '_ONLINE_USER', $nodeId),
            count($data),
            3600
        );
        Cache::put(
            CacheKey::get('SERVER_' . strtoupper($nodeType) . '_LAST_PUSH_AT', $nodeId),
            time(),
            3600
        );

        $userService = new UserService();
        $userService->trafficFetch($node, $nodeType, $data);
        return $this->success(true);
    }

    // 后端获取配置
    public function config(Request $request)
    {
        $node = $this->getNodeInfo($request);
        $nodeType = $node->type;
        $protocolSettings = $node->protocol_settings;

        $serverPort = $node->server_port;
        $host = $node->host;

        $baseConfig = [
            'protocol' => $nodeType,
            'listen_ip' => '0.0.0.0',
            'server_port' => (int) $serverPort,
            'network' => data_get($protocolSettings, 'network'),
            'networkSettings' => data_get($protocolSettings, 'network_settings') ?: null,
        ];

        $response = match ($nodeType) {
            'shadowsocks' => [
                ...$baseConfig,
                'cipher' => $protocolSettings['cipher'],
                'plugin' => $protocolSettings['plugin'],
                'plugin_opts' => $protocolSettings['plugin_opts'],
                'server_key' => match ($protocolSettings['cipher']) {
                        '2022-blake3-aes-128-gcm' => Helper::getServerKey($node->created_at, 16),
                        '2022-blake3-aes-256-gcm' => Helper::getServerKey($node->created_at, 32),
                        default => null
                    }
            ],
            'vmess' => [
                ...$baseConfig,
                'tls' => (int) $protocolSettings['tls']
            ],
            'trojan' => [
                ...$baseConfig,
                'host' => $host,
                'server_name' => $protocolSettings['server_name'],
            ],
            'vless' => [
                ...$baseConfig,
                'tls' => (int) $protocolSettings['tls'],
                'flow' => $protocolSettings['flow'],
                'tls_settings' =>
                        match ((int) $protocolSettings['tls']) {
                            2 => $protocolSettings['reality_settings'],
                            default => $protocolSettings['tls_settings']
                        }
            ],
            'hysteria' => [
                ...$baseConfig,
                'server_port' => (int) $serverPort,
                'version' => (int) $protocolSettings['version'],
                'host' => $host,
                'server_name' => $protocolSettings['tls']['server_name'],
                'up_mbps' => (int) $protocolSettings['bandwidth']['up'],
                'down_mbps' => (int) $protocolSettings['bandwidth']['down'],
                ...match ((int) $protocolSettings['version']) {
                        1 => ['obfs' => $protocolSettings['obfs']['password'] ?? null],
                        2 => [
                            'obfs' => $protocolSettings['obfs']['open'] ? $protocolSettings['obfs']['type'] : null,
                            'obfs-password' => $protocolSettings['obfs']['password'] ?? null
                        ],
                        default => []
                    }
            ],
            'tuic' => [
                ...$baseConfig,
                'version' => (int) $protocolSettings['version'],
                'server_port' => (int) $serverPort,
                'server_name' => $protocolSettings['tls']['server_name'],
                'congestion_control' => $protocolSettings['congestion_control'],
                'auth_timeout' => '3s',
                'zero_rtt_handshake' => false,
                'heartbeat' => "3s",
            ],
            'anytls' => [
                ...$baseConfig,
                'server_port' => (int) $serverPort,
                'server_name' => $protocolSettings['tls']['server_name'],
                'padding_scheme' => $protocolSettings['padding_scheme'],
            ],
            'socks' => [
                ...$baseConfig,
                'server_port' => (int) $serverPort,
            ],
            'naive' => [
                ...$baseConfig,
                'server_port' => (int) $serverPort,
                'tls' => (int) $protocolSettings['tls'],
                'tls_settings' => $protocolSettings['tls_settings']
            ],
            'http' => [
                ...$baseConfig,
                'server_port' => (int) $serverPort,
                'tls' => (int) $protocolSettings['tls'],
                'tls_settings' => $protocolSettings['tls_settings']
            ],
            'mieru' => [
                ...$baseConfig,
                'server_port' => (string) $serverPort,
                'protocol' => (int) $protocolSettings['protocol'],
            ],
            default => []
        };

        $response['base_config'] = [
            'push_interval' => (int) admin_setting('server_push_interval', 60),
            'pull_interval' => (int) admin_setting('server_pull_interval', 60)
        ];

        if (!empty($node['route_ids'])) {
            $response['routes'] = ServerService::getRoutes($node['route_ids']);
        }

        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match', ''), $eTag) !== false) {
            return response(null, 304);
        }
        return response($response)->header('ETag', "\"{$eTag}\"");
    }

    // 获取在线用户数据（wyx2685
    public function alivelist(Request $request): JsonResponse
    {
        $node = $this->getNodeInfo($request);
        $deviceLimitUsers = ServerService::getAvailableUsers($node)
            ->where('device_limit', '>', 0);
        $alive = $this->userOnlineService->getAliveList($deviceLimitUsers);
        return response()->json(['alive' => (object) $alive]);
    }

    // 后端提交在线数据
    public function alive(Request $request): JsonResponse
    {
        $node = $this->getNodeInfo($request);
        $data = json_decode(request()->getContent(), true);
        if ($data === null) {
            return response()->json([
                'error' => 'Invalid online data'
            ], 400);
        }
        UpdateAliveDataJob::dispatch($data, $node->type, $node->id);
        return response()->json(['data' => true]);
    }

    // 提交节点负载状态
    public function status(Request $request): JsonResponse
    {
        $node = $this->getNodeInfo($request);

        $data = $request->validate([
            'cpu' => 'required|numeric|min:0|max:100',
            'mem.total' => 'required|integer|min:0',
            'mem.used' => 'required|integer|min:0',
            'swap.total' => 'required|integer|min:0',
            'swap.used' => 'required|integer|min:0',
            'disk.total' => 'required|integer|min:0',
            'disk.used' => 'required|integer|min:0',
        ]);

        $nodeType = $node->type;
        $nodeId = $node->id;

        $statusData = [
            'cpu' => (float) $data['cpu'],
            'mem' => [
                'total' => (int) $data['mem']['total'],
                'used' => (int) $data['mem']['used'],
            ],
            'swap' => [
                'total' => (int) $data['swap']['total'],
                'used' => (int) $data['swap']['used'],
            ],
            'disk' => [
                'total' => (int) $data['disk']['total'],
                'used' => (int) $data['disk']['used'],
            ],
            'updated_at' => now()->timestamp,
        ];

        $cacheTime = max(300, (int) admin_setting('server_push_interval', 60) * 3);
        cache([
            CacheKey::get('SERVER_' . strtoupper($nodeType) . '_LOAD_STATUS', $nodeId) => $statusData,
            CacheKey::get('SERVER_' . strtoupper($nodeType) . '_LAST_LOAD_AT', $nodeId) => now()->timestamp,
        ], $cacheTime);

        return response()->json(['data' => true, "code" => 0, "message" => "success"]);
    }
}
