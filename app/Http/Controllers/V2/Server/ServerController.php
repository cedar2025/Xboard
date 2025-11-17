<?php

namespace App\Http\Controllers\V2\Server;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use Illuminate\Http\Request;
use App\Utils\Helper;


class ServerController extends Controller
{
    private $nodeInfo;
    private $nodeId;

    public function __construct(Request $request)
    {
        $token = $request->input('token');
        if (empty($token)) {
            abort(500, 'token is null');
        }
        if ($token !== admin_setting('server_token')) {
            abort(500, 'token is error');
        }
        $this->nodeId = $request->input('node_id');
        $this->serverService = new ServerService();
        $this->nodeInfo = $this->serverService->getServer($this->nodeId, "v2node");
        if (!$this->nodeInfo) abort(500, 'server is not exist');
    }

    // 后端获取配置
    public function config(Request $request)
    {
        $protocolSettings = $node->protocol_settings;
        $response = [
            'listen_ip' => $this->nodeInfo->host,
            'server_port' => $this->nodeInfo->server_port,
            'network' => data_get($protocolSettings, 'network'),
            'network_settings' => data_get($protocolSettings, 'network_settings') ?: null,
            'protocol' => $this->nodeInfo->type,
            'tls' => (int) $protocolSettings['tls'],
            'tls_settings' => match ((int) $protocolSettings['tls']) {
                            2 => $protocolSettings['reality_settings'],
                            default => $protocolSettings['tls_settings']
                        },
            'encryption' => data_get($protocolSettings, 'encryption'),
            'encryption_settings' => data_get($protocolSettings, 'encryption_settings') ?: null,
            'flow' => $protocolSettings['flow'],
            'cipher' => $protocolSettings['cipher'],
            'congestion_control' => $protocolSettings['congestion_control'],
            'zero_rtt_handshake' => $protocolSettings['zero_rtt_handshake'] ? true : false,
            'up_mbps' => $protocolSettings['bandwidth']['up'],
            'down_mbps' => $protocolSettings['bandwidth']['down'],
            'obfs' => $protocolSettings['obfs']['open'] ? $protocolSettings['obfs']['type'] : null,
            'obfs_password' => $protocolSettings['obfs']['password'] ?? null,
            'padding_scheme' => $protocolSettings['padding_scheme']
        ];
        if ($protocolSettings['cipher'] === '2022-blake3-aes-128-gcm') {
            $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 16);
        }
        if ($protocolSettings['cipher'] === '2022-blake3-aes-256-gcm') {
            $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 32);
        }

        if ($protocolSettings['bandwidth']['up'] == 0 && $protocolSettings['bandwidth']['down'] == 0) {
            $response['ignore_client_bandwidth'] = true;
        } else {
            $response['ignore_client_bandwidth'] = false;
        }

        $response['base_config'] = [
            'push_interval' => (int) admin_setting('server_push_interval', 60),
            'pull_interval' => (int) admin_setting('server_pull_interval', 60),
            'node_report_min_traffic' => (int) admin_setting('server_node_report_min_traffic', 0),
            'device_online_min_traffic' => (int) admin_setting('server_device_online_min_traffic', 0)
        ];
        if ($this->nodeInfo['route_id']) {
            $response['routes'] = ServerService::getRoutes($node['route_ids']);
        }
        $rsp = json_encode($response);
        $eTag = sha1($rsp);
        if (strpos($request->header('If-None-Match'), $eTag) !== false) {
            abort(304);
        }
        return response($response)->header('ETag', "\"{$eTag}\"");
    }
}