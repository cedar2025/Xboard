<?php

namespace App\Protocols;

use App\Utils\Helper;
use App\Support\AbstractProtocol;

class Shadowrocket extends AbstractProtocol
{
    public $flags = ['shadowrocket'];

    protected $protocolRequirements = [
        'shadowrocket' => [
            'hysteria' => [
                'protocol_settings.version' => [
                    '2' => '1993'
                ],
            ],
            'anytls' => [
                'base_version' => '2592'
            ],
        ],
    ];

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;

        $uri = '';
        //display remaining traffic and expire date
        $upload = round($user['u'] / (1024 * 1024 * 1024), 2);
        $download = round($user['d'] / (1024 * 1024 * 1024), 2);
        $totalTraffic = round($user['transfer_enable'] / (1024 * 1024 * 1024), 2);
        $expiredDate = date('Y-m-d', $user['expired_at']);
        $uri .= "STATUS=🚀↑:{$upload}GB,↓:{$download}GB,TOT:{$totalTraffic}GB💡Expires:{$expiredDate}\r\n";
        foreach ($servers as $item) {
            if ($item['type'] === 'shadowsocks') {
                $uri .= self::buildShadowsocks($item['password'], $item);
            }
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($item['password'], $item);
            }
            if ($item['type'] === 'vless') {
                $uri .= self::buildVless($item['password'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($item['password'], $item);
            }
            if ($item['type'] === 'hysteria') {
                $uri .= self::buildHysteria($item['password'], $item);
            }
            if ($item['type'] === 'tuic') {
                $uri .= self::buildTuic($item['password'], $item);
            }
            if ($item['type'] === 'anytls') {
                $uri .= self::buildAnyTLS($item['password'], $item);
            }
            if ($item['type'] === 'socks') {
                $uri .= self::buildSocks($item['password'], $item);
            }
        }
        return response(base64_encode($uri))
            ->header('content-type', 'text/plain');
    }


    public static function buildShadowsocks($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $name = rawurlencode($server['name']);
        $password = data_get($server, 'password', $password);
        $str = str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode("{$protocol_settings['cipher']}:{$password}")
        );
        $addr = Helper::wrapIPv6($server['host']);

        $uri = "ss://{$str}@{$addr}:{$server['port']}";
        $plugin = data_get($protocol_settings, 'plugin') == 'obfs' ? 'obfs-local' : data_get($protocol_settings, 'plugin');
        $plugin_opts = data_get($protocol_settings, 'plugin_opts');
        if ($plugin && $plugin_opts) {
            $uri .= '/?' . 'plugin=' . $plugin . ';' . rawurlencode($plugin_opts);
        }
        return $uri . "#{$name}\r\n";
    }

    public static function buildVmess($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $userinfo = base64_encode('auto:' . $uuid . '@' . Helper::wrapIPv6($server['host']) . ':' . $server['port']);
        $config = [
            'tfo' => 1,
            'remark' => $server['name'],
            'alterId' => 0
        ];
        if ($protocol_settings['tls']) {
            $config['tls'] = 1;
            if (data_get($protocol_settings, 'tls_settings')) {
                if (!!data_get($protocol_settings, 'tls_settings.allow_insecure'))
                    $config['allowInsecure'] = (int) data_get($protocol_settings, 'tls_settings.allow_insecure');
                if (!!data_get($protocol_settings, 'tls_settings.server_name'))
                    $config['peer'] = data_get($protocol_settings, 'tls_settings.server_name');
            }
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                if (data_get($protocol_settings, 'network_settings.header.type', 'none') !== 'none') {
                    $config['obfs'] = data_get($protocol_settings, 'network_settings.header.type');
                    $config['path'] = \Illuminate\Support\Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/']));
                    $config['obfsParam'] = \Illuminate\Support\Arr::random(data_get($protocol_settings, 'network_settings.header.request.headers.Host', ['www.example.com']));
                }
                break;
            case 'ws':
                $config['obfs'] = "websocket";
                $config['path'] = data_get($protocol_settings, 'network_settings.path');
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host')) {
                    $config['obfsParam'] = $host;
                }
                break;
            case 'grpc':
                $config['obfs'] = "grpc";
                $config['path'] = data_get($protocol_settings, 'network_settings.serviceName');
                $config['host'] = data_get($protocol_settings, 'tls_settings.server_name') ?? $server['host'];
                break;
        }
        $query = http_build_query($config, '', '&', PHP_QUERY_RFC3986);
        $uri = "vmess://{$userinfo}?{$query}";
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildVless($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $userinfo = base64_encode('auto:' . $uuid . '@' . Helper::wrapIPv6($server['host']) . ':' . $server['port']);
        $config = [
            'tfo' => 1,
            'remark' => $server['name'],
            'alterId' => 0
        ];

        // 判断是否开启xtls
        if (data_get($protocol_settings, 'flow')) {
            $xtlsMap = [
                'none' => 0,
                'xtls-rprx-direct' => 1,
                'xtls-rprx-vision' => 2
            ];
            if (array_key_exists(data_get($protocol_settings, 'flow'), $xtlsMap)) {
                $config['tls'] = 1;
                $config['xtls'] = $xtlsMap[data_get($protocol_settings, 'flow')];
            }
        }

        switch (data_get($protocol_settings, 'tls')) {
            case 1:
                $config['tls'] = 1;
                $config['allowInsecure'] = (int) data_get($protocol_settings, 'tls_settings.allow_insecure');
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $config['peer'] = $serverName;
                }
                break;
            case 2:
                $config['tls'] = 1;
                $config['sni'] = data_get($protocol_settings, 'reality_settings.server_name');
                $config['pbk'] = data_get($protocol_settings, 'reality_settings.public_key');
                $config['sid'] = data_get($protocol_settings, 'reality_settings.short_id');
                $config['fp'] = Helper::getRandFingerprint();
                break;
            default:
                break;
        }
        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                if (data_get($protocol_settings, 'network_settings.header.type', 'none') !== 'none') {
                    $config['obfs'] = data_get($protocol_settings, 'network_settings.header.type');
                    $config['path'] = \Illuminate\Support\Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/']));
                    $config['obfsParam'] = \Illuminate\Support\Arr::random(data_get($protocol_settings, 'network_settings.header.request.headers.Host', ['www.example.com']));
                }
                break;
            case 'ws':
                $config['obfs'] = "websocket";
                if (data_get($protocol_settings, 'network_settings.path')) {
                    $config['path'] = data_get($protocol_settings, 'network_settings.path');
                }

                if ($host = data_get($protocol_settings, 'network_settings.headers.Host')) {
                    $config['obfsParam'] = $host;
                }
                break;
            case 'grpc':
                $config['obfs'] = "grpc";
                $config['path'] = data_get($protocol_settings, 'network_settings.serviceName');
                $config['host'] = data_get($protocol_settings, 'tls_settings.server_name') ?? $server['host'];
                break;
        }

        $query = http_build_query($config, '', '&', PHP_QUERY_RFC3986);
        $uri = "vless" . "://{$userinfo}?{$query}";
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildTrojan($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $name = rawurlencode($server['name']);
        $params['allowInsecure'] = data_get($protocol_settings, 'allow_insecure');
        if ($serverName = data_get($protocol_settings, 'server_name')) {
            $params['peer'] = $serverName;
        }
        switch (data_get($protocol_settings, 'network')) {
            case 'grpc':
                $params['obfs'] = 'grpc';
                $params['path'] = data_get($protocol_settings, 'network_settings.serviceName');
                break;
            case 'ws':
                $host = data_get($protocol_settings, 'network_settings.headers.Host');
                $path = data_get($protocol_settings, 'network_settings.path');
                $params['plugin'] = "obfs-local;obfs=websocket;obfs-host={$host};obfs-uri={$path}";
                break;
        }
        $query = http_build_query($params);
        $addr = Helper::wrapIPv6($server['host']);

        $uri = "trojan://{$password}@{$addr}:{$server['port']}?{$query}&tfo=1#{$name}";
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildHysteria($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $uri = ''; // 初始化变量

        switch (data_get($protocol_settings, 'version')) {
            case 1:
                $params = [
                    "auth" => $password,
                    "upmbps" => data_get($protocol_settings, 'bandwidth.up'),
                    "downmbps" => data_get($protocol_settings, 'bandwidth.down'),
                    "protocol" => 'udp',
                    "fastopen" => 1,
                ];
                if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
                    $params['peer'] = $serverName;
                }
                if (data_get($protocol_settings, 'obfs.open')) {
                    $params["obfs"] = "xplus";
                    $params["obfsParam"] = data_get($protocol_settings, 'obfs_settings.password');
                }
                $params['insecure'] = data_get($protocol_settings, 'tls.allow_insecure');
                if (isset($server['ports']))
                    $params['mport'] = $server['ports'];
                $query = http_build_query($params);
                $addr = Helper::wrapIPv6($server['host']);

                $uri = "hysteria://{$addr}:{$server['port']}?{$query}#{$server['name']}";
                $uri .= "\r\n";
                break;
            case 2:
                $params = [
                    "obfs" => 'none',
                    "fastopen" => 1
                ];
                if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
                    $params['peer'] = $serverName;
                }
                if (data_get($protocol_settings, 'obfs.open')) {
                    $params['obfs'] = data_get($protocol_settings, 'obfs.type');
                    $params['obfs-password'] = data_get($protocol_settings, 'obfs.password');
                }
                $params['insecure'] = data_get($protocol_settings, 'tls.allow_insecure');
                if (isset($protocol_settings['hop_interval'])) {
                    $params['keepalive'] = $protocol_settings['hop_interval'];
                }
                if (isset($server['ports'])) {
                    $params['mport'] = $server['ports'];
                }
                $query = http_build_query($params);
                $addr = Helper::wrapIPv6($server['host']);

                $uri = "hysteria2://{$password}@{$addr}:{$server['port']}?{$query}#{$server['name']}";
                $uri .= "\r\n";
                break;
        }
        return $uri;
    }
    public static function buildTuic($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $name = rawurlencode($server['name']);
        $params = [
            'alpn' => data_get($protocol_settings, 'alpn'),
            'sni' => data_get($protocol_settings, 'tls.server_name'),
            'insecure' => data_get($protocol_settings, 'tls.allow_insecure')
        ];
        if (data_get($protocol_settings, 'version') === 4) {
            $params['token'] = $password;
        } else {
            $params['uuid'] = $password;
            $params['password'] = $password;
        }
        $query = http_build_query($params);
        $uri = "tuic://{$server['host']}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildAnyTLS($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $name = rawurlencode($server['name']);
        $params = [
            'sni' => data_get($protocol_settings, 'tls.server_name'),
            'insecure' => data_get($protocol_settings, 'tls.allow_insecure')
        ];
        $query = http_build_query($params);
        $uri = "anytls://{$password}@{$server['host']}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildSocks($password, $server)
    {
        $uri = "socks://" . base64_encode("{$password}:{$password}@{$server['host']}:{$server['port']}") . "?method=auto";
        $uri .= "\r\n";
        return $uri;
    }
}
