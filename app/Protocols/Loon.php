<?php

namespace App\Protocols;

use App\Support\AbstractProtocol;

class Loon extends AbstractProtocol
{
    public $flags = ['loon'];

    protected $protocolRequirements = [
        'loon' => [
            'hysteria' => [
                'protocol_settings.version' => [
                    '2' => '637'
                ],
            ],
        ],
    ];

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;

        $uri = '';

        foreach ($servers as $item) {
            if (
                $item['type'] === 'shadowsocks'
            ) {
                $uri .= self::buildShadowsocks($item['password'], $item);
            }
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($item['password'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($item['password'], $item);
            }
            if ($item['type'] === 'hysteria') {
                $uri .= self::buildHysteria($item['password'], $item, $user);
            }
        }
        return response($uri)
            ->header('content-type', 'text/plain')
            ->header('Subscription-Userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}");
    }


    public static function buildShadowsocks($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $cipher = data_get($protocol_settings, 'cipher');

        $config = [
            "{$server['name']}=Shadowsocks",
            "{$server['host']}",
            "{$server['port']}",
            "{$cipher}",
            "{$password}",
            'fast-open=false',
            'udp=true'
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
                    $config[] = "obfs-name={$parsedOpts['obfs']}";
                    if (isset($parsedOpts['obfs-host'])) {
                        $config[] = "obfs-host={$parsedOpts['obfs-host']}";
                    }
                    if (isset($parsedOpts['path'])) {
                        $config[] = "obfs-uri={$parsedOpts['path']}";
                    }
                    break;
            }
        }

        $config = array_filter($config);
        $uri = implode(',', $config) . "\r\n";
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
                $config[] = 'over-tls=true';
            if (data_get($protocol_settings, 'tls_settings')) {
                $tls_settings = data_get($protocol_settings, 'tls_settings');
                $config[] = 'skip-cert-verify=' . ($tls_settings['allow_insecure'] ? 'true' : 'false');
                if (data_get($tls_settings, 'server_name'))
                    $config[] = "tls-name={$tls_settings['server_name']}";
            }
        }

        switch (data_get($server['protocol_settings'], 'network')) {
            case 'tcp':
                $config[] = 'transport=tcp';
                $tcpSettings = data_get($protocol_settings, 'network_settings');
                if (data_get($tcpSettings, 'header.type'))
                    $config = str_replace('transport=tcp', "transport={$tcpSettings['header']['type']}", $config);
                if (data_get($tcpSettings, key: 'header.request.path')) {
                    $paths = data_get($tcpSettings, key: 'header.request.path');
                    $path = $paths[array_rand($paths)];
                    $config[] = "path={$path}";
                }
                if (data_get($tcpSettings, key: 'header.request.headers.Host')) {
                    $hosts = data_get($tcpSettings, key: 'header.request.headers.Host');
                    $host = $hosts[array_rand($hosts)];
                    $config[] = "host={$host}";
                }
                break;
            case 'ws':
                $config[] = 'transport=ws';
                $wsSettings = data_get($protocol_settings, 'network_settings');
                if (data_get($wsSettings, key: 'path'))
                    $config[] = "path={$wsSettings['path']}";
                if (data_get($wsSettings, key: 'headers.Host'))
                    $config[] = "host={$wsSettings['headers']['Host']}";
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
            $config[] = data_get($protocol_settings, 'allow_insecure') ? 'skip-cert-verify=true' : 'skip-cert-verify=false';
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildVless($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $config = [
            "{$server['name']}=vless",
            $server['host'],
            $server['port'],
            $uuid,
            'fast-open=false',
            'udp=true',
            'alterId=0'
        ];
        switch ((int) data_get($protocol_settings, 'tls')) {
            case 1:
                $config[] = 'over-tls=true';
                $tlsSettings = data_get($protocol_settings, 'tls_settings', []);
                if ($tlsSettings) {
                    $config[] = 'skip-cert-verify=' . (data_get($tlsSettings, 'allow_insecure') ? 'true' : 'false');
                    if ($serverName = data_get($tlsSettings, 'server_name')) {
                        $config[] = "tls-name={$serverName}";
                    }
                }
                break;
            case 2:
                return '';
        }
        $network_settings = data_get($protocol_settings, 'network_settings', []);
        switch ((string) data_get($network_settings, 'network')) {
            case 'tcp':
                $config[] = 'transport=tcp';
                if ($headerType = data_get($network_settings, 'header.type')) {
                    $config = collect($config)->map(function ($item) use ($headerType) {
                        return $item === 'transport=tcp' ? "transport={$headerType}" : $item;
                    })->toArray();
                }
                if ($paths = data_get($network_settings, 'header.request.path')) {
                    $config[] = 'path=' . $paths[array_rand($paths)];
                }
                break;
            case 'ws':
                $config[] = 'transport=ws';
                if ($path = data_get($network_settings, 'path')) {
                    $config[] = "path={$path}";
                }

                if ($host = data_get($network_settings, 'headers.Host')) {
                    $config[] = "host={$host}";
                }
                break;
        }
        return implode(',', $config) . "\r\n";
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
