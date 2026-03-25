<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Services\DeviceStateService;
use App\Services\NodeSyncService;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class UniProxyController extends Controller
{
    public function __construct(
        private readonly DeviceStateService $deviceStateService
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
        $response = ServerService::buildNodeConfig($node);

        $response['base_config'] = [
            'push_interval' => (int) admin_setting('server_push_interval', 60),
            'pull_interval' => (int) admin_setting('server_pull_interval', 60)
        ];

        $eTag = sha1(json_encode($response));
        if (strpos($request->header('If-None-Match', ''), $eTag) !== false) {
            return response(null, 304);
        }
        return response($response)->header('ETag', "\"{$eTag}\"");
    }

    // 获取在线用户数据
    public function alivelist(Request $request): JsonResponse
    {
        $node = $this->getNodeInfo($request);
        $deviceLimitUsers = ServerService::getAvailableUsers($node)
            ->where('device_limit', '>', 0);

        $alive = $this->deviceStateService->getAliveList(collect($deviceLimitUsers));

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

        foreach ($data as $uid => $ips) {
            $this->deviceStateService->setDevices((int) $uid, $node->id, $ips);
        }

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
