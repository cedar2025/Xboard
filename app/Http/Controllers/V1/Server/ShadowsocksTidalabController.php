<?php

namespace App\Http\Controllers\V1\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\ServerShadowsocks;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/*
 * Tidal Lab Shadowsocks
 * Github: https://github.com/tokumeikoi/tidalab-ss
 */
class ShadowsocksTidalabController extends Controller
{
    // 后端获取用户
    public function user(Request $request)
    {
        ini_set('memory_limit', -1);
        $nodeId = $request->input('node_id');
        $server = ServerShadowsocks::find($nodeId);
        if (!$server) {
            return $this->fail([400,'节点不存在']);
        }
        Cache::put(CacheKey::get('SERVER_SHADOWSOCKS_LAST_CHECK_AT', $server->id), time(), 3600);
        $users = ServerService::getAvailableUsers($server->group_id);
        $result = [];
        foreach ($users as $user) {
            array_push($result, [
                'id' => $user->id,
                'port' => $server->server_port,
                'cipher' => $server->cipher,
                'secret' => $user->uuid
            ]);
        }
        $eTag = sha1(json_encode($result));
        if (strpos($request->header('If-None-Match'), $eTag) !== false ) {
            return response(null,304);
        }
        return response([
            'data' => $result
        ])->header('ETag', "\"{$eTag}\"");
    }

    // 后端提交数据
    public function submit(Request $request)
    {
        $server = ServerShadowsocks::find($request->input('node_id'));
        if (!$server) {
            return response([
                'ret' => 0,
                'msg' => 'server is not found'
            ]);
        }
        $data = get_request_content();
        $data = json_decode($data, true);
        Cache::put(CacheKey::get('SERVER_SHADOWSOCKS_ONLINE_USER', $server->id), count($data), 3600);
        Cache::put(CacheKey::get('SERVER_SHADOWSOCKS_LAST_PUSH_AT', $server->id), time(), 3600);
        $userService = new UserService();
        $formatData = [];

        foreach ($data as $item) {
            $formatData[$item['user_id']] = [$item['u'], $item['d']];
        }
        $userService->trafficFetch($server->toArray(), 'shadowsocks', $formatData);

        return response([
            'ret' => 1,
            'msg' => 'ok'
        ]);
    }
}
