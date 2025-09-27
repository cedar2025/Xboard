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
        $server = $request->attributes->get('node_info');
        Cache::put(CacheKey::get('SERVER_SHADOWSOCKS_LAST_CHECK_AT', $server->id), time(), 3600);
        $users = ServerService::getAvailableUsers($server);
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
        $server = $request->attributes->get('node_info');
        $data = json_decode(request()->getContent(), true);
        Cache::put(CacheKey::get('SERVER_SHADOWSOCKS_ONLINE_USER', $server->id), count($data), 3600);
        Cache::put(CacheKey::get('SERVER_SHADOWSOCKS_LAST_PUSH_AT', $server->id), time(), 3600);
        $userService = new UserService();
        $formatData = [];

        foreach ($data as $item) {
            $formatData[$item['user_id']] = [$item['u'], $item['d']];
        }
        $userService->trafficFetch($server, 'shadowsocks', $formatData);

        return response([
            'ret' => 1,
            'msg' => 'ok'
        ]);
    }
}