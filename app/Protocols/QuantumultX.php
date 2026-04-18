<?php

namespace App\Protocols;

use App\Utils\Helper;
use App\Support\AbstractProtocol;
use App\Models\Server;

class QuantumultX extends AbstractProtocol
{
    public $flags = ['quantumult%20x', 'quantumult-x'];
    public $allowedProtocols = [
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_VMESS,
        Server::TYPE_VLESS,
        Server::TYPE_TROJAN,
        Server::TYPE_ANYTLS,
        Server::TYPE_SOCKS,
        Server::TYPE_HTTP,
    ];

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $uri = '';
        foreach ($servers as $item) {
            $uri .= match ($item['type']) {
                Server::TYPE_SHADOWSOCKS => self::buildShadowsocks($item['password'], $item),
                Server::TYPE_VMESS => self::buildVmess($item['password'], $item),
                Server::TYPE_VLESS => self::buildVless($item['password'], $item),
                Server::TYPE_TROJAN => self::buildTrojan($item['password'], $item),
                Server::TYPE_ANYTLS => self::buildAnyTLS($item['password'], $item),
                Server::TYPE_SOCKS => self::buildSocks5($item['password'], $item),
                Server::TYPE_HTTP => self::buildHttp($item['password'], $item),
                default => ''
            };
        }
        return response(base64_encode($uri))
            ->header('content-type', 'text/plain')
            ->header('subscription-userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}");
    }

    public static function buildShadowsocks($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $password = data_get($server, 'password', $password);
        $addr = Helper::wrapIPv6($server['host']);
        $config = [
            "shadowsocks={$addr}:{$server['port']}",
            "method=" . data_get($protocol_settings, 'cipher'),
            "password={$password}",
        ];

        if (data_get($protocol_settings, 'plugin') && data_get($protocol_settings, 'plugin_opts')) {
            $plugin = data_get($protocol_settings, 'plugin');
            $pluginOpts = data_get($protocol_settings, 'plugin_opts', '');
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
            if ($plugin === 'obfs') {
                if (isset($parsedOpts['obfs'])) {
                    $config[] = "obfs={$parsedOpts['obfs']}";
                }
                if (isset($parsedOpts['obfs-host'])) {
                    $config[] = "obfs-host={$parsedOpts['obfs-host']}";
                }
                if (isset($parsedOpts['path'])) {
                    $config[] = "obfs-uri={$parsedOpts['path']}";
                }
            }
        }

        self::applyCommonSettings($config, $server);

        return implode(',', array_filter($config)) . "\r\n";
    }

    public static function buildVmess($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $addr = Helper::wrapIPv6($server['host']);
        $config = [
            "vmess={$addr}:{$server['port']}",
            "method=" . data_get($protocol_settings, 'cipher', 'auto'),
            "password={$uuid}",
        ];

        self::applyTransportSettings($config, $protocol_settings);
        self::applyCommonSettings($config, $server);

        return implode(',', array_filter($config)) . "\r\n";
    }

    public static function buildVless($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $addr = Helper::wrapIPv6($server['host']);
        $config = [
            "vless={$addr}:{$server['port']}",
            'method=none',
            "password={$uuid}",
        ];

        self::applyTransportSettings($config, $protocol_settings);

        if ($flow = data_get($protocol_settings, 'flow')) {
            $config[] = "vless-flow={$flow}";
        }

        self::applyCommonSettings($config, $server);

        return implode(',', array_filter($config)) . "\r\n";
    }

    private static function applyTransportSettings(&$config, $settings, bool $nativeTls = false, ?array $tlsData = null)
    {
        $tlsMode = (int) data_get($settings, 'tls', 0);
        $network = data_get($settings, 'network', 'tcp');
        $host = null;
        $isWs = $network === 'ws';

        switch ($network) {
            case 'ws':
                $config[] = $tlsMode ? 'obfs=wss' : 'obfs=ws';
                if ($path = data_get($settings, 'network_settings.path')) {
                    $config[] = "obfs-uri={$path}";
                }
                $host = data_get($settings, 'network_settings.headers.Host');
                break;
            case 'tcp':
                $headerType = data_get($settings, 'network_settings.header.type', 'tcp');
                if ($headerType === 'http') {
                    $config[] = 'obfs=http';
                    $paths = data_get($settings, 'network_settings.header.request.path', ['/']);
                    $config[] = 'obfs-uri=' . (is_array($paths) ? ($paths[0] ?? '/') : $paths);
                    $hostVal = data_get($settings, 'network_settings.header.request.headers.Host');
                    $host = is_array($hostVal) ? ($hostVal[0] ?? null) : $hostVal;
                } elseif ($tlsMode) {
                    $config[] = $nativeTls ? 'over-tls=true' : 'obfs=over-tls';
                }
                break;
        }

        switch ($tlsMode) {
            case 2: // Reality
                $host = $host ?? data_get($settings, 'reality_settings.server_name');
                if ($pubKey = data_get($settings, 'reality_settings.public_key')) {
                    $config[] = "reality-base64-pubkey={$pubKey}";
                }
                if ($shortId = data_get($settings, 'reality_settings.short_id')) {
                    $config[] = "reality-hex-shortid={$shortId}";
                }
                break;
            case 1: // TLS
                $resolved = $tlsData ?? (array) data_get($settings, 'tls_settings', []);
                $allowInsecure = (bool) ($resolved['allow_insecure'] ?? false);
                $config[] = 'tls-verification=' . ($allowInsecure ? 'false' : 'true');
                $host = $host ?? ($resolved['server_name'] ?? null);
                break;
        }

        if ($host) {
            $config[] = ($nativeTls && !$isWs) ? "tls-host={$host}" : "obfs-host={$host}";
        }
    }

    private static function applyCommonSettings(&$config, $server)
    {
        $config[] = 'fast-open=true';
        if ($server['type'] !== Server::TYPE_HTTP) {
            $config[] = 'udp-relay=true';
        }
        $config[] = "tag={$server['name']}";
    }

    public static function buildTrojan($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $addr = Helper::wrapIPv6($server['host']);
        $config = [
            "trojan={$addr}:{$server['port']}",
            "password={$password}",
        ];

        $tlsData = [
            'allow_insecure' => data_get($protocol_settings, 'tls_settings.allow_insecure', false),
            'server_name' => data_get($protocol_settings, 'tls_settings.server_name'),
        ];
        self::applyTransportSettings($config, $protocol_settings, true, $tlsData);
        self::applyCommonSettings($config, $server);

        return implode(',', array_filter($config)) . "\r\n";
    }

    public static function buildAnyTLS($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $addr = Helper::wrapIPv6($server['host']);
        $config = [
            "anytls={$addr}:{$server['port']}",
            "password={$password}",
            'udp-relay=true',
            "tag={$server['name']}",
            "over-tls=true",
        ];

        // allow_insecure=false => tls-verification=true；
        // allow_insecure=true 时不写，沿用 QX 默认 false
        $allowInsecure = (bool) data_get($protocol_settings, 'tls.allow_insecure', false);
        if (!$allowInsecure) {
            $config[] = 'tls-verification=true';
        }

        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $config[] = "tls-host=$serverName";
        }

        return implode(',', array_filter($config)) . "\r\n";
    }

    public static function buildSocks5($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $addr = Helper::wrapIPv6($server['host']);
        $config = [
            "socks5={$addr}:{$server['port']}",
            "username={$password}",
            "password={$password}",
        ];

        self::applyTransportSettings($config, $protocol_settings, true);
        self::applyCommonSettings($config, $server);

        return implode(',', array_filter($config)) . "\r\n";
    }

    public static function buildHttp($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $addr = Helper::wrapIPv6($server['host']);
        $config = [
            "http={$addr}:{$server['port']}",
            "username={$password}",
            "password={$password}",
        ];

        self::applyTransportSettings($config, $protocol_settings, true);
        self::applyCommonSettings($config, $server);

        return implode(',', array_filter($config)) . "\r\n";
    }
}
