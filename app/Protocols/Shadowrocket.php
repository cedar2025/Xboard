<?php

namespace App\Protocols;

use App\Utils\Helper;
use App\Support\AbstractProtocol;
use App\Models\Server;
use Illuminate\Support\Arr;

class Shadowrocket extends AbstractProtocol
{
    public $flags = ['shadowrocket'];
    public $allowedProtocols = [
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_VMESS,
        Server::TYPE_VLESS,
        Server::TYPE_TROJAN,
        Server::TYPE_HYSTERIA,
        Server::TYPE_TUIC,
        Server::TYPE_ANYTLS,
        Server::TYPE_SOCKS,
    ];

    protected $protocolRequirements = [
        'shadowrocket.hysteria.protocol_settings.version' => [2 => '1993'],
        'shadowrocket.anytls.base_version' => '2592',
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
        $expiredDate = $user['expired_at'] === null ? 'N/A' : date('Y-m-d', $user['expired_at']);
        $uri .= "STATUS=🚀↑:{$upload}GB,↓:{$download}GB,TOT:{$totalTraffic}GB💡Expires:{$expiredDate}\r\n";
        foreach ($servers as $item) {
            if ($item['type'] === Server::TYPE_SHADOWSOCKS) {
                $uri .= self::buildShadowsocks($item['password'], $item);
            }
            if ($item['type'] === Server::TYPE_VMESS) {
                $uri .= self::buildVmess($item['password'], $item);
            }
            if ($item['type'] === Server::TYPE_VLESS) {
                $uri .= self::buildVless($item['password'], $item);
            }
            if ($item['type'] === Server::TYPE_TROJAN) {
                $uri .= self::buildTrojan($item['password'], $item);
            }
            if ($item['type'] === Server::TYPE_HYSTERIA) {
                $uri .= self::buildHysteria($item['password'], $item);
            }
            if ($item['type'] === Server::TYPE_TUIC) {
                $uri .= self::buildTuic($item['password'], $item);
            }
            if ($item['type'] === Server::TYPE_ANYTLS) {
                $uri .= self::buildAnyTLS($item['password'], $item);
            }
            if ($item['type'] === Server::TYPE_SOCKS) {
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
            base64_encode(data_get($protocol_settings, 'cipher') . ":{$password}")
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
        $config = [
            "v" => "2",
            "ps" => $server['name'],
            "add" => $server['host'],
            "port" => (string) $server['port'],
            "id" => $uuid,
            "aid" => '0',
            "net" => data_get($server, 'protocol_settings.network'),
            "type" => "none",
            "host" => "",
            "path" => "",
            "tls" => data_get($protocol_settings, 'tls') ? "tls" : "",
            "tfo" => 1, // TCP Fast Open
        ];
        if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
            $config['sni'] = $serverName;
        }
        if ($fp = Helper::getTlsFingerprint(data_get($protocol_settings, 'utls'))) {
            $config['fp'] = $fp;
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                if (data_get($protocol_settings, 'network_settings.header.type', 'none') !== 'none') {
                    $config['type'] = data_get($protocol_settings, 'network_settings.header.type', 'http');
                    $config['path'] = Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/']));
                    $config['host'] =
                        data_get($protocol_settings, 'network_settings.header.request.headers.Host')
                        ? Arr::random(data_get($protocol_settings, 'network_settings.header.request.headers.Host', ['/']), )
                        : null;
                }
                break;
            case 'ws':
                $config['type'] = 'ws';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host'))
                    $config['host'] = $host;
                break;
            case 'grpc':
                $config['type'] = 'grpc';
                if ($path = data_get($protocol_settings, 'network_settings.serviceName'))
                    $config['path'] = $path;
                break;
            case 'h2':
                $config['net'] = 'h2';
                $config['type'] = 'h2';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                if ($host = data_get($protocol_settings, 'network_settings.host'))
                    $config['host'] = is_array($host) ? implode(',', $host) : $host;
                break;
            case 'httpupgrade':
                $config['net'] = 'httpupgrade';
                $config['type'] = 'httpupgrade';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                $config['host'] = data_get($protocol_settings, 'network_settings.host', $server['host']);
                break;
            default:
                break;
        }
        return "vmess://" . base64_encode(json_encode($config)) . "\r\n";
    }

    public static function buildVless($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $host = $server['host']; //节点地址
        $port = $server['port']; //节点端口
        $name = $server['name']; //节点名称

        $config = [
            'mode' => 'multi', //grpc传输模式
            'security' => '', //传输层安全 tls/reality
            'encryption' => match (data_get($protocol_settings, 'encryption.enabled')) {
                true => data_get($protocol_settings, 'encryption.encryption', 'none'),
                default => 'none'
            },
            'type' => data_get($server, 'protocol_settings.network'), //传输协议
            'flow' => data_get($protocol_settings, 'flow'),
            'tfo' => 1,  // TCP Fast Open
        ];
        // 处理TLS
        switch (data_get($server, 'protocol_settings.tls')) {
            case 1:
                $config['security'] = "tls";
                if ($fp = Helper::getTlsFingerprint(data_get($protocol_settings, 'utls'))) {
                    $config['fp'] = $fp;
                }
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $config['sni'] = $serverName;
                }
                if (data_get($protocol_settings, 'tls_settings.allow_insecure')) {
                    $config['allowInsecure'] = '1';
                }
                break;
            case 2: //reality
                $config['security'] = "reality";
                $config['pbk'] = data_get($protocol_settings, 'reality_settings.public_key');
                $config['sid'] = data_get($protocol_settings, 'reality_settings.short_id');
                $config['sni'] = data_get($protocol_settings, 'reality_settings.server_name');
                $config['servername'] = data_get($protocol_settings, 'reality_settings.server_name');
                $config['spx'] = "/";
                if ($fp = Helper::getTlsFingerprint(data_get($protocol_settings, 'utls'))) {
                    $config['fp'] = $fp;
                }
                break;
            default:
                break;
        }
        // 处理传输协议
        switch (data_get($server, 'protocol_settings.network')) {
            case 'ws':
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                if ($wsHost = data_get($protocol_settings, 'network_settings.headers.Host'))
                    $config['host'] = $wsHost;
                break;
            case 'grpc':
                if ($path = data_get($protocol_settings, 'network_settings.serviceName'))
                    $config['serviceName'] = $path;
                break;
            case 'h2':
                $config['type'] = 'http';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                if ($h2Host = data_get($protocol_settings, 'network_settings.host'))
                    $config['host'] = is_array($h2Host) ? implode(',', $h2Host) : $h2Host;
                break;
            case 'kcp':
                if ($path = data_get($protocol_settings, 'network_settings.seed'))
                    $config['path'] = $path;
                $config['type'] = data_get($protocol_settings, 'network_settings.header.type', 'none');
                break;
            case 'httpupgrade':
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                $config['host'] = data_get($protocol_settings, 'network_settings.host', $server['host']);
                break;
            case 'xhttp':
                $config['path'] = data_get($protocol_settings, 'network_settings.path');
                $config['host'] = data_get($protocol_settings, 'network_settings.host', $server['host']);
                $config['mode'] = data_get($protocol_settings, 'network_settings.mode', 'auto');
                $config['extra'] = json_encode(data_get($protocol_settings, 'network_settings.extra'));
                break;
        }

        $user = $uuid . '@' . Helper::wrapIPv6($host) . ':' . $port;
        $query = http_build_query($config);
        $fragment = urlencode($name);
        $link = sprintf("vless://%s?%s#%s\r\n", $user, $query, $fragment);
        return $link;
    }

    public static function buildTrojan($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $name = rawurlencode($server['name']);
        $params = [];
        $tlsMode = (int) data_get($protocol_settings, 'tls', 1);

        switch ($tlsMode) {
            case 2: // Reality
                $params['security'] = 'reality';
                $params['pbk'] = data_get($protocol_settings, 'reality_settings.public_key');
                $params['sid'] = data_get($protocol_settings, 'reality_settings.short_id');
                $params['sni'] = data_get($protocol_settings, 'reality_settings.server_name');
                break;
            default: // Standard TLS
                $params['allowInsecure'] = data_get($protocol_settings, 'allow_insecure');
                if ($serverName = data_get($protocol_settings, 'server_name')) {
                    $params['peer'] = $serverName;
                }
                break;
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
                    $params["obfsParam"] = data_get($protocol_settings, 'obfs.password');
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
                    $params['keepalive'] = data_get($protocol_settings, 'hop_interval');
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
        $addr = Helper::wrapIPv6($server['host']);
        $uri = "tuic://{$addr}:{$server['port']}?{$query}#{$name}";
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
        $addr = Helper::wrapIPv6($server['host']);
        $uri = "anytls://{$password}@{$addr}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildSocks($password, $server)
    {   
        $protocol_settings = $server['protocol_settings'];
        $name = rawurlencode($server['name']);
        $addr = Helper::wrapIPv6($server['host']);
        $uri = 'socks://' . base64_encode("{$password}:{$password}@{$addr}:{$server['port']}") . "?method=auto#{$name}";
        $uri .= "\r\n";
        return $uri;
    }
}
