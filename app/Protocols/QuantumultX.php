<?php

namespace App\Protocols;

use App\Support\AbstractProtocol;
use App\Models\Server;

class QuantumultX extends AbstractProtocol
{
    public $flags = ['quantumult%20x', 'quantumult-x', 'quantumultx', 'quantumult x'];
    public $allowedProtocols = [
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_VMESS,
        Server::TYPE_VLESS,
        Server::TYPE_TROJAN,
    ];

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $uri = '';
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
        }
        return response(base64_encode($uri))
            ->header('content-type', 'text/plain')
            ->header('subscription-userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}");
    }

    public static function buildShadowsocks($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $password = data_get($server, 'password', $password);
        $config = [
            "shadowsocks={$server['host']}:{$server['port']}",
            "method={$protocol_settings['cipher']}",
            "password={$password}",
            'fast-open=true',
            'udp-relay=true',
            "tag={$server['name']}"
        ];
        if (data_get($protocol_settings, 'plugin') && data_get($protocol_settings, 'plugin_opts')) {
            $plugin = data_get($protocol_settings, 'plugin');
            $pluginOpts = data_get($protocol_settings, 'plugin_opts', '');
            // 解析插件选项
            $parsedOpts = collect(explode(';', $pluginOpts))
                ->filter()
                ->mapWithKeys(function ($pair) {
                    if (!str_contains($pair, '=')) {
                        return [];
                    }
                    [$key, $value] = explode('=', $pair, 2);
                    return [trim($key) => trim($value)];
                })
                ->all();
            switch ($plugin) {
                case 'obfs':
                    $config[] = "obfs={$parsedOpts['obfs']}";
                    if (isset($parsedOpts['obfs-host'])) {
                        $config[] = "obfs-host={$parsedOpts['obfs-host']}";
                    }
                    if (isset($parsedOpts['path'])) {
                        $config[] = "obfs-uri={$parsedOpts['path']}";
                    }
                    break;
            }
        }
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildVmess($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $config = [
            "vmess={$server['host']}:{$server['port']}",
            'method=chacha20-poly1305',
            "password={$uuid}",
            'fast-open=true',
            'udp-relay=true',
            "tag={$server['name']}"
        ];

        if (data_get($protocol_settings, 'tls')) {
            if (data_get($protocol_settings, 'network') === 'tcp')
                array_push($config, 'obfs=over-tls');
            if (data_get($protocol_settings, 'tls_settings')) {
                if (data_get($protocol_settings, 'tls_settings.allow_insecure'))
                    array_push($config, 'tls-verification=' . ($protocol_settings['tls_settings']['allow_insecure'] ? 'false' : 'true'));
                if (data_get($protocol_settings, 'tls_settings.server_name'))
                    $host = data_get($protocol_settings, 'tls_settings.server_name');
            }
        }
        if (data_get($protocol_settings, 'network') === 'ws') {
            if (data_get($protocol_settings, 'tls'))
                array_push($config, 'obfs=wss');
            else
                array_push($config, 'obfs=ws');
            if (data_get($protocol_settings, 'network_settings')) {
                if (data_get($protocol_settings, 'network_settings.path'))
                    array_push($config, "obfs-uri={$protocol_settings['network_settings']['path']}");
                if (data_get($protocol_settings, 'network_settings.headers.Host') && !isset($host))
                    $host = data_get($protocol_settings, 'network_settings.headers.Host');
            }
        }
        if (isset($host)) {
            array_push($config, "obfs-host={$host}");
        }

        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildVless($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $config = [
            "vless={$server['host']}:{$server['port']}",
            'method=none',
            "password={$uuid}",
            'fast-open=false',
            'udp-relay=true',
            "tag={$server['name']}"
        ];

        $tls = (int) data_get($protocol_settings, 'tls', 0);
        $network = data_get($protocol_settings, 'network');
        $obfsHost = null;

        switch ($tls) {
            case 1:
                if ($network === 'tcp') {
                    $config[] = 'obfs=over-tls';
                }
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $obfsHost = $serverName;
                }
                $allowInsecure = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
                $config[] = 'tls-verification=' . ($allowInsecure ? 'false' : 'true');
                break;
            case 2:
                if ($network === 'tcp') {
                    $config[] = 'obfs=over-tls';
                }
                if ($serverName = data_get($protocol_settings, 'reality_settings.server_name')) {
                    $obfsHost = $serverName;
                }
                if ($publicKey = data_get($protocol_settings, 'reality_settings.public_key')) {
                    $config[] = "reality-base64-pubkey={$publicKey}";
                }
                if ($shortId = data_get($protocol_settings, 'reality_settings.short_id')) {
                    $config[] = "reality-hex-shortid={$shortId}";
                }
                if ($flow = data_get($protocol_settings, 'flow')) {
                    $config[] = "vless-flow={$flow}";
                }
                break;
        }

        switch ($network) {
            case 'tcp':
                $headerType = data_get($protocol_settings, 'network_settings.header.type', 'none');
                if ($tls === 0 && $headerType === 'http') {
                    $config[] = 'obfs=http';
                    $obfsHost = self::firstValue(data_get($protocol_settings, 'network_settings.header.request.headers.Host'));
                    if ($path = self::firstValue(data_get($protocol_settings, 'network_settings.header.request.path'))) {
                        $config[] = "obfs-uri={$path}";
                    }
                }
                break;
            case 'ws':
                $config[] = $tls === 0 ? 'obfs=ws' : 'obfs=wss';
                if (!$obfsHost) {
                    $obfsHost = data_get($protocol_settings, 'network_settings.headers.Host');
                }
                if ($path = data_get($protocol_settings, 'network_settings.path')) {
                    $config[] = "obfs-uri={$path}";
                }
                break;
        }

        if ($obfsHost) {
            $config[] = "obfs-host={$obfsHost}";
        }

        $uri = implode(',', self::filterConfig($config));
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildTrojan($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $config = [
            "trojan={$server['host']}:{$server['port']}",
            "password={$password}",
            'over-tls=true',
            $protocol_settings['server_name'] ? "tls-host={$protocol_settings['server_name']}" : "",
            // Tips: allowInsecure=false = tls-verification=true
            $protocol_settings['allow_insecure'] ? 'tls-verification=false' : 'tls-verification=true',
            'fast-open=true',
            'udp-relay=true',
            "tag={$server['name']}"
        ];
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    private static function firstValue($value)
    {
        if (is_array($value)) {
            return reset($value) ?: null;
        }

        return $value ?: null;
    }

    private static function filterConfig(array $config): array
    {
        return array_values(array_filter($config, fn($item) => $item !== null && $item !== ''));
    }
}
