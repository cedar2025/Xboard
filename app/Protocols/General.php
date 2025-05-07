<?php

namespace App\Protocols;


use App\Contracts\ProtocolInterface;
use App\Utils\Helper;
use Illuminate\Support\Arr;
class General implements ProtocolInterface
{
    public $flags = ['general', 'v2rayn', 'v2rayng', 'passwall', 'ssrplus', 'sagernet'];
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $uri = '';

        foreach ($servers as $item) {
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($user['uuid'], $item);
            }
            if ($item['type'] === 'vless') {
                $uri .= self::buildVless($user['uuid'], $item);
            }
            if ($item['type'] === 'shadowsocks') {
                $uri .= self::buildShadowsocks($item['password'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($user['uuid'], $item);
            }
            if ($item['type'] === 'hysteria') {
                $uri .= self::buildHysteria($user['uuid'], $item);
            }
            if ($item['type'] === 'socks') {
                $uri .= self::buildSocks($user['uuid'], $item);
            }
        }
        return base64_encode($uri);
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
        return "ss://{$str}@{$addr}:{$server['port']}#{$name}\r\n";
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
            "net" => $server['protocol_settings']['network'],
            "type" => "none",
            "host" => "",
            "path" => "",
            "tls" => $protocol_settings['tls'] ? "tls" : "",
        ];
        if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
            $config['sni'] = $serverName;
        }

        switch ($protocol_settings['network']) {
            case 'tcp':
                if (data_get($protocol_settings, 'network_settings.header.type', 'none') !== 'none') {
                    $config['type'] = data_get($protocol_settings, 'network_settings.header.type', 'http');
                    $config['path'] = Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/']));
                    $config['host'] = 
                    data_get($protocol_settings, 'network_settings.headers.Host') 
                    ? Arr::random(data_get($protocol_settings, 'network_settings.headers.Host', ['/']), ) 
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
            'encryption' => 'none', //加密方式
            'type' => $server['protocol_settings']['network'], //传输协议
            'flow' => $protocol_settings['flow'] ? $protocol_settings['flow'] : null,
        ];
        // 处理TLS
        switch ($server['protocol_settings']['tls']) {
            case 1:
                $config['security'] = "tls";
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $config['sni'] = $serverName;
                }
                break;
            case 2: //reality
                $config['security'] = "reality";
                $config['pbk'] = data_get($protocol_settings, 'reality_settings.public_key');
                $config['sid'] = data_get($protocol_settings, 'reality_settings.short_id');
                $config['sni'] = data_get($protocol_settings, 'reality_settings.server_name');
                $config['servername'] = data_get($protocol_settings, 'reality_settings.server_name');
                $config['spx'] = "/";
                $config['fp'] = Helper::getRandFingerprint();
                break;
            default:
                break;
        }
        // 处理传输协议
        switch ($server['protocol_settings']['network']) {
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
        $array = [];
        $array['allowInsecure'] = $protocol_settings['allow_insecure'];
        if ($serverName = data_get($protocol_settings, 'server_name')) {
            $array['peer'] = $serverName;
            $array['sni'] = $serverName;
        }
        switch ($server['protocol_settings']['network']) {
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
        // Return empty if version is not 2
        if ($server['protocol_settings']['version'] !== 2) {
            return '';
        }

        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $params['sni'] = $serverName;
            $params['security'] = 'tls';
        }

        if (data_get($protocol_settings, 'obfs.open')) {
            $params['obfs'] = 'salamander';
            $params['obfs-password'] = data_get($protocol_settings, 'obfs.password');
        }
        if (isset($server['ports'])) {
            $params['mport'] = $server['ports'];
        }

        $params['insecure'] = data_get($protocol_settings, 'tls.allow_insecure');

        $query = http_build_query($params);
        $name = rawurlencode($server['name']);
        $addr = Helper::wrapIPv6($server['host']);

        $uri = "hysteria2://{$password}@{$addr}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";

        return $uri;
    }

    public static function buildSocks($password, $server)
    {
        $name = rawurlencode($server['name']);
        $credentials = base64_encode("{$password}:{$password}");
        return "socks://{$credentials}@{$server['host']}:{$server['port']}#{$name}\r\n";
    }

}
