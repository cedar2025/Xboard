<?php

namespace App\Http\Controllers\V1\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\ServerTrojan;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/*
 * Tidal Lab Trojan
 * Github: https://github.com/tokumeikoi/tidalab-trojan
 */
class TrojanTidalabController extends Controller
{
    const TROJAN_CONFIG = '{"run_type":"server","local_addr":"0.0.0.0","local_port":443,"remote_addr":"www.taobao.com","remote_port":80,"password":[],"ssl":{"cert":"server.crt","key":"server.key","sni":"domain.com"},"api":{"enabled":true,"api_addr":"127.0.0.1","api_port":10000}}';

    // 后端获取用户
    public function user(Request $request)
    {
        ini_set('memory_limit', -1);
        $server = $request->attributes->get('node_info');
        if ($server->type !== 'trojan') {
            return $this->fail([400, '节点不存在']);
        }
        Cache::put(CacheKey::get('SERVER_TROJAN_LAST_CHECK_AT', $server->id), time(), 3600);
        $users = ServerService::getAvailableUsers($server);
        $result = [];
        foreach ($users as $user) {
            $user->trojan_user = [
                "password" => $user->uuid,
            ];
            unset($user->uuid);
            array_push($result, $user);
        }
        $eTag = sha1(json_encode($result));
        if (strpos($request->header('If-None-Match'), $eTag) !== false) {
            return response(null, 304);
        }
        return response([
            'msg' => 'ok',
            'data' => $result,
        ])->header('ETag', "\"{$eTag}\"");
    }

    // 后端提交数据
    public function submit(Request $request)
    {
        $server = $request->attributes->get('node_info');
        if ($server->type !== 'trojan') {
            return $this->fail([400, '节点不存在']);
        }
        $data = json_decode(request()->getContent(), true);
        Cache::put(CacheKey::get('SERVER_TROJAN_ONLINE_USER', $server->id), count($data), 3600);
        Cache::put(CacheKey::get('SERVER_TROJAN_LAST_PUSH_AT', $server->id), time(), 3600);
        $userService = new UserService();
        $formatData = [];
        foreach ($data as $item) {
            $formatData[$item['user_id']] = [$item['u'], $item['d']];
        }
        $userService->trafficFetch($server, 'trojan', $formatData);

        return response([
            'ret' => 1,
            'msg' => 'ok'
        ]);
    }

    // 后端获取配置
    public function config(Request $request)
    {
        $server = $request->attributes->get('node_info');
        if ($server->type !== 'trojan') {
            return $this->fail([400, '节点不存在']);
        }
        $request->validate([
            'node_id' => 'required',
            'local_port' => 'required'
        ], [
            'node_id.required' => '节点ID不能为空',
            'local_port.required' => '本地端口不能为空'
        ]);
        try {
            $json = $this->getTrojanConfig($server, $request->input('local_port'));
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500, '配置获取失败']);
        }

        return (json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    private function getTrojanConfig($server, int $localPort)
    {
        $protocolSettings = $server->protocol_settings;
        $json = json_decode(self::TROJAN_CONFIG);
        $json->local_port = $server->server_port;
        $json->ssl->sni = data_get($protocolSettings, 'server_name', $server->host);
        $json->ssl->cert = "/root/.cert/server.crt";
        $json->ssl->key = "/root/.cert/server.key";
        $json->api->api_port = $localPort;
        return $json;
    }
}