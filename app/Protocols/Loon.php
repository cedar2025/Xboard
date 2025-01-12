<?php

namespace App\Protocols;

use App\Contracts\ProtocolInterface;

class Loon implements ProtocolInterface
{
    public $flags = ['loon'];
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
            if (
                $item['type'] === 'shadowsocks'
                && in_array(data_get($item['protocol_settings'], 'cipher'), [
                    'aes-128-gcm',
                    'aes-192-gcm',
                    'aes-256-gcm',
                    'chacha20-ietf-poly1305'
                ])
            ) {
                $uri .= self::buildShadowsocks($item['password'], $item);
            }
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($user['uuid'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($user['uuid'], $item);
            }
            if ($item['type'] === 'hysteria') {
                $uri .= self::buildHysteria($user['uuid'], $item, $user);
            }
        }
        return response($uri, 200)
            ->header('Subscription-Userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}");
    }


    public static function buildShadowsocks($password, $server)
    {
        $cipher = data_get($server['protocol_settings'], 'cipher');
        $config = [
            "{$server['name']}=Shadowsocks",
            "{$server['host']}",
            "{$server['port']}",
            "{$cipher}",
            "{$password}",
            'fast-open=false',
            'udp=true'
        ];
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildVmess($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $config = [
            "{$server['name']}=vmess",
            "{$server['host']}",
            "{$server['port']}",
            'auto',
            "{$uuid}",
            'fast-open=false',
            'udp=true',
            "alterId=0"
        ];

        if (data_get($protocol_settings, 'tls')) {
            if (data_get($protocol_settings, 'network') === 'tcp')
                array_push($config, 'over-tls=true');
            if (data_get($protocol_settings, 'tls_settings')) {
                $tls_settings = data_get($protocol_settings, 'tls_settings');
                if (data_get($tls_settings, 'allow_insecure'))
                    array_push($config, 'skip-cert-verify=' . ($tls_settings['allow_insecure'] ? 'true' : 'false'));
                if (data_get($tls_settings, 'server_name'))
                    array_push($config, "tls-name={$tls_settings['server_name']}");
            }
        }

        switch (data_get($server['protocol_settings'], 'network')) {
            case 'tcp':
                array_push($config, 'transport=tcp');
                $tcpSettings = data_get($protocol_settings, 'network_settings');
                if (data_get($protocol_settings, 'network_settings')['header']['type'])
                    $config = str_replace('transport=tcp', "transport={$tcpSettings['header']['type']}", $config);
                if (data_get($tcpSettings, key: 'header.request.path')) {
                    $paths = data_get($tcpSettings, key: 'header.request.path');
                    $path = $paths[array_rand($paths)];
                    array_push($config, "path={$path}");
                }
                if (data_get($tcpSettings, key: 'header.request.headers.Host')) {
                    $hosts = data_get($tcpSettings, key: 'header.request.headers.Host');
                    $host = $hosts[array_rand($hosts)];
                    array_push($config, "host={$host}");
                }
                break;
            case 'ws':
                array_push($config, 'transport=ws');
                $wsSettings = data_get($protocol_settings, 'network_settings');
                if (data_get($wsSettings, key: 'path'))
                    array_push($config, "path={$wsSettings['path']}");
                if (data_get($wsSettings, key: 'headers.Host'))
                    array_push($config, "host={$wsSettings['headers']['Host']}");
                break;


        }

        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildTrojan($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $config = [
            "{$server['name']}=trojan",
            "{$server['host']}",
            "{$server['port']}",
            "{$password}",
            data_get($protocol_settings, 'server_name') ? "tls-name={$protocol_settings['server_name']}" : "",
            'fast-open=false',
            'udp=true'
        ];
        if (!empty($protocol_settings['allow_insecure'])) {
            array_push($config, data_get($protocol_settings, 'tls_settings')['allow_insecure'] ? 'skip-cert-verify=true' : 'skip-cert-verify=false');
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildHysteria($password, $server, $user)
    {
        $protocol_settings = $server['protocol_settings'];
        if ($protocol_settings['version'] != 2) {
            return;
        }
        $config = [
            "{$server['name']}=Hysteria2",
            $server['host'],
            $server['port'],
            $password,
            $protocol_settings['tls']['server_name'] ? "sni={$protocol_settings['tls']['server_name']}" : "(null)"
        ];
        if (data_get($protocol_settings, 'tls.allow_insecure'))
            $config[] = "skip-cert-verify=true";
        $config[] = "download-bandwidth=" . data_get($protocol_settings, 'bandwidth.download_bandwidth');
        $config[] = "udp=true";
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }
}
