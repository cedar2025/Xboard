<?php

namespace App\Protocols;

use App\Models\Server;
use App\Utils\Helper;
use Illuminate\Support\Arr;
use App\Support\AbstractProtocol;

class General extends AbstractProtocol
{
    public $flags = ['general', 'v2rayn', 'v2rayng', 'passwall', 'ssrplus', 'sagernet'];

    public $allowedProtocols = [
        Server::TYPE_VMESS,
        Server::TYPE_VLESS,
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_TROJAN,
        Server::TYPE_HYSTERIA,
        Server::TYPE_ANYTLS,
        Server::TYPE_SOCKS,
        Server::TYPE_TUIC,
        Server::TYPE_HTTP,
    ];

    protected $protocolRequirements = [
        'v2rayng.hysteria.protocol_settings.version' => [2 => '1.9.5'],
        'v2rayn.hysteria.protocol_settings.version' => [2 => '6.31'],
    ];

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $uri = '';

        foreach ($servers as $item) {
            $uri .= match ($item['type']) {
                Server::TYPE_VMESS => self::buildVmess($item['password'], $item),
                Server::TYPE_VLESS => self::buildVless($item['password'], $item),
                Server::TYPE_SHADOWSOCKS => self::buildShadowsocks($item['password'], $item),
                Server::TYPE_TROJAN => self::buildTrojan($item['password'], $item),
                Server::TYPE_HYSTERIA => self::buildHysteria($item['password'], $item),
                Server::TYPE_ANYTLS => self::buildAnyTLS($item['password'], $item),
                Server::TYPE_SOCKS => self::buildSocks($item['password'], $item),
                Server::TYPE_TUIC => self::buildTuic($item['password'], $item),
                Server::TYPE_HTTP => self::buildHttp($item['password'], $item),
                default => '',
            };
        }
        return response(base64_encode($uri))
            ->header('content-type', 'text/plain')
            ->header('subscription-userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}");
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
        $plugin = data_get($protocol_settings, 'plugin');
        $plugin_opts = data_get($protocol_settings, 'plugin_opts');
        $url = "ss://{$str}@{$addr}:{$server['port']}";
        if ($plugin && $plugin_opts) {
            $url .= '/?' . 'plugin=' . rawurlencode($plugin . ';' . $plugin_opts);
        }
        $url .= "#{$name}\r\n";
        return $url;
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
            case 'xhttp':
                $config['net'] = 'xhttp';
                $config['type'] = 'xhttp';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                $config['host'] = data_get($protocol_settings, 'network_settings.host', $server['host']);
                if ($mode = data_get($protocol_settings, 'network_settings.mode', 'auto'))
                    $config['mode'] = $mode;
                if ($extra = data_get($protocol_settings, 'network_settings.extra'))
                    $config['extra'] = is_array($extra) && !empty($extra) ? json_encode($extra) : null;
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
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $config['path'] = $path;
                $config['host'] = data_get($protocol_settings, 'network_settings.host', $server['host']);
                if ($mode = data_get($protocol_settings, 'network_settings.mode', 'auto'))
                    $config['mode'] = $mode;
                if ($extra = data_get($protocol_settings, 'network_settings.extra'))
                    $config['extra'] = is_array($extra) && !empty($extra) ? json_encode($extra) : null;
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
        $array = [];
        $tlsMode = (int) data_get($protocol_settings, 'tls', 1);

        switch ($tlsMode) {
            case 2: // Reality
                $array['security'] = 'reality';
                $array['pbk'] = data_get($protocol_settings, 'reality_settings.public_key');
                $array['sid'] = data_get($protocol_settings, 'reality_settings.short_id');
                $array['sni'] = data_get($protocol_settings, 'reality_settings.server_name');
                if ($fp = Helper::getTlsFingerprint(data_get($protocol_settings, 'utls'))) {
                    $array['fp'] = $fp;
                }
                break;
            default: // Standard TLS
                $array['allowInsecure'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $array['peer'] = $serverName;
                    $array['sni'] = $serverName;
                }
                if ($fp = Helper::getTlsFingerprint(data_get($protocol_settings, 'utls'))) {
                    $array['fp'] = $fp;
                }
                break;
        }

        switch (data_get($server, 'protocol_settings.network')) {
            case 'ws':
                $array['type'] = 'ws';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $array['path'] = $path;
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host'))
                    $array['host'] = $host;
                break;
            case 'grpc':
                // Follow V2rayN family standards
                $array['type'] = 'grpc';
                if ($serviceName = data_get($protocol_settings, 'network_settings.serviceName'))
                    $array['serviceName'] = $serviceName;
                break;
            case 'h2':
                $array['type'] = 'http';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $array['path'] = $path;
                if ($host = data_get($protocol_settings, 'network_settings.host'))
                    $array['host'] = is_array($host) ? implode(',', $host) : $host;
                break;
            case 'httpupgrade':
                $array['type'] = 'httpupgrade';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $array['path'] = $path;
                $array['host'] = data_get($protocol_settings, 'network_settings.host', $server['host']);
                break;
            case 'xhttp':
                $array['type'] = 'xhttp';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $array['path'] = $path;
                $array['host'] = data_get($protocol_settings, 'network_settings.host', $server['host']);
                if ($mode = data_get($protocol_settings, 'network_settings.mode', 'auto'))
                    $array['mode'] = $mode;
                if ($extra = data_get($protocol_settings, 'network_settings.extra'))
                    $array['extra'] = is_array($extra) && !empty($extra) ? json_encode($extra) : null;
                break;
            default:
                break;
        }
        $query = http_build_query($array);
        $addr = Helper::wrapIPv6($server['host']);

        $uri = "trojan://{$password}@{$addr}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildHysteria($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $params = [];
        $version = data_get($protocol_settings, 'version', 2);

        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $params['sni'] = $serverName;
        }
        $params['insecure'] = data_get($protocol_settings, 'tls.allow_insecure') ? '1' : '0';

        $name = rawurlencode($server['name']);
        $addr = Helper::wrapIPv6($server['host']);

        if ($version === 2) {
            if (data_get($protocol_settings, 'obfs.open')) {
                $params['obfs'] = 'salamander';
                $params['obfs-password'] = data_get($protocol_settings, 'obfs.password');
            }
            if (isset($server['ports'])) {
                $params['mport'] = $server['ports'];
            }

            $query = http_build_query($params);
            $uri = "hysteria2://{$password}@{$addr}:{$server['port']}?{$query}#{$name}";
        } else {
            $params['protocol'] = 'udp';
            $params['auth'] = $password;
            if ($upMbps = data_get($protocol_settings, 'bandwidth.up'))
                $params['upmbps'] = $upMbps;
            if ($downMbps = data_get($protocol_settings, 'bandwidth.down'))
                $params['downmbps'] = $downMbps;
            if (data_get($protocol_settings, 'obfs.open') && ($obfsPassword = data_get($protocol_settings, 'obfs.password'))) {
                $params['obfs'] = 'xplus';
                $params['obfsParam'] = $obfsPassword;
            }

            $query = http_build_query($params);
            $uri = "hysteria://{$addr}:{$server['port']}?{$query}#{$name}";
        }
        $uri .= "\r\n";

        return $uri;
    }


    public static function buildTuic($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $name = rawurlencode($server['name']);
        $addr = Helper::wrapIPv6($server['host']);
        $port = $server['port'];
        $uuid = $password; // v2rayN格式里，uuid和password都是密码部分
        $pass = $password;

        $queryParams = [];

        // 填充sni参数
        if ($sni = data_get($protocol_settings, 'tls.server_name')) {
            $queryParams['sni'] = $sni;
        }

        // alpn参数，支持多值时用逗号连接
        if ($alpn = data_get($protocol_settings, 'alpn')) {
            if (is_array($alpn)) {
                $queryParams['alpn'] = implode(',', $alpn);
            } else {
                $queryParams['alpn'] = $alpn;
            }
        }

        // congestion_controller参数，默认cubic
        $congestion = data_get($protocol_settings, 'congestion_control', 'cubic');
        $queryParams['congestion_control'] = $congestion;

        // udp_relay_mode参数，默认native
        $udpRelay = data_get($protocol_settings, 'udp_relay_mode', 'native');
        $queryParams['udp-relay-mode'] = $udpRelay;

        if (data_get($protocol_settings, 'tls.allow_insecure')) {
            $queryParams['insecure'] = '1';
        }

        $query = http_build_query($queryParams);

        // 构造完整URI，格式：
        // Tuic://uuid:password@host:port?sni=xxx&alpn=xxx&congestion_controller=xxx&udp_relay_mode=xxx#别名
        $uri = "tuic://{$uuid}:{$pass}@{$addr}:{$port}";

        if (!empty($query)) {
            $uri .= "?{$query}";
        }

        $uri .= "#{$name}\r\n";

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
        $name = rawurlencode($server['name']);
        $credentials = base64_encode("{$password}:{$password}");
        $addr = Helper::wrapIPv6($server['host']);
        return "socks://{$credentials}@{$addr}:{$server['port']}#{$name}\r\n";
    }

    public static function buildHttp($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $name = rawurlencode($server['name']);
        $addr = Helper::wrapIPv6($server['host']);
        $credentials = base64_encode("{$password}:{$password}");

        $params = [];
        if (data_get($protocol_settings, 'tls')) {
            $params['security'] = 'tls';
            if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                $params['sni'] = $serverName;
            }
            $params['allowInsecure'] = data_get($protocol_settings, 'tls_settings.allow_insecure') ? '1' : '0';
        }

        $uri = "http://{$credentials}@{$addr}:{$server['port']}";
        if (!empty($params)) {
            $uri .= '?' . http_build_query($params);
        }
        $uri .= "#{$name}\r\n";
        return $uri;
    }
}
