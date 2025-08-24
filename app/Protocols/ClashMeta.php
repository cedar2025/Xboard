<?php

namespace App\Protocols;

use App\Models\Server;
use App\Utils\Helper;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use App\Support\AbstractProtocol;

class ClashMeta extends AbstractProtocol
{
    public $flags = ['meta', 'verge', 'flclash', 'nekobox', 'clashmetaforandroid'];
    const CUSTOM_TEMPLATE_FILE = 'resources/rules/custom.clashmeta.yaml';
    const CUSTOM_CLASH_TEMPLATE_FILE = 'resources/rules/custom.clash.yaml';
    const DEFAULT_TEMPLATE_FILE = 'resources/rules/default.clash.yaml';
    public $allowedProtocols = [
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_VMESS,
        Server::TYPE_TROJAN,
        Server::TYPE_VLESS,
        Server::TYPE_HYSTERIA,
        Server::TYPE_TUIC,
        Server::TYPE_ANYTLS,
        Server::TYPE_SOCKS,
        Server::TYPE_HTTP,
        Server::TYPE_MIERU,
    ];

    protected $protocolRequirements = [
        '*.vless.protocol_settings.network' => [
            'whitelist' => [
                'tcp' => '0.0.0',
                'ws' => '0.0.0',
                'grpc' => '0.0.0',
                'http' => '0.0.0',
                'h2' => '0.0.0',
            ],
            'strict' => true,
        ],
        'nekobox.hysteria.protocol_settings.version' => [
            1 => '0.0.0',
            2 => '1.2.7',
        ],
        'clashmetaforandroid.hysteria.protocol_settings.version' => [
            2 => '2.9.0',
        ],
        'nekoray.hysteria.protocol_settings.version' => [
            2 => '3.24',
        ],
        'verge.hysteria.protocol_settings.version' => [
            2 => '1.3.8',
        ],
        'ClashX Meta.hysteria.protocol_settings.version' => [
            2 => '1.3.5',
        ],
        'flclash.hysteria.protocol_settings.version' => [
            2 => '0.8.0',
        ],
    ];

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $appName = admin_setting('app_name', 'XBoard');

        $template = admin_setting('subscribe_template_clashmeta', File::exists(base_path(self::CUSTOM_TEMPLATE_FILE))
            ? File::get(base_path(self::CUSTOM_TEMPLATE_FILE))
            : (
                File::exists(base_path(self::CUSTOM_CLASH_TEMPLATE_FILE))
                ? File::get(base_path(self::CUSTOM_CLASH_TEMPLATE_FILE))
                : File::get(base_path(self::DEFAULT_TEMPLATE_FILE))
            ));

        $config = Yaml::parse($template);
        $proxy = [];
        $proxies = [];

        foreach ($servers as $item) {
            if ($item['type'] === Server::TYPE_SHADOWSOCKS) {
                array_push($proxy, self::buildShadowsocks($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_VMESS) {
                array_push($proxy, self::buildVmess($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_TROJAN) {
                array_push($proxy, self::buildTrojan($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_VLESS) {
                array_push($proxy, self::buildVless($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_HYSTERIA) {
                array_push($proxy, self::buildHysteria($item['password'], $item, $user));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_TUIC) {
                array_push($proxy, self::buildTuic($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_ANYTLS) {
                array_push($proxy, self::buildAnyTLS($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_SOCKS) {
                array_push($proxy, self::buildSocks5($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_HTTP) {
                array_push($proxy, self::buildHttp($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_MIERU) {
                array_push($proxy, self::buildMieru($item['password'], $item));
                array_push($proxies, $item['name']);
            }
        }

        $config['proxies'] = array_merge($config['proxies'] ? $config['proxies'] : [], $proxy);
        foreach ($config['proxy-groups'] as $k => $v) {
            if (!is_array($config['proxy-groups'][$k]['proxies']))
                $config['proxy-groups'][$k]['proxies'] = [];
            $isFilter = false;
            foreach ($config['proxy-groups'][$k]['proxies'] as $src) {
                foreach ($proxies as $dst) {
                    if (!$this->isRegex($src))
                        continue;
                    $isFilter = true;
                    $config['proxy-groups'][$k]['proxies'] = array_values(array_diff($config['proxy-groups'][$k]['proxies'], [$src]));
                    if ($this->isMatch($src, $dst)) {
                        array_push($config['proxy-groups'][$k]['proxies'], $dst);
                    }
                }
                if ($isFilter)
                    continue;
            }
            if ($isFilter)
                continue;
            $config['proxy-groups'][$k]['proxies'] = array_merge($config['proxy-groups'][$k]['proxies'], $proxies);
        }
        $config['proxy-groups'] = array_filter($config['proxy-groups'], function ($group) {
            return $group['proxies'];
        });
        $config['proxy-groups'] = array_values($config['proxy-groups']);
        $config = $this->buildRules($config);

        $yaml = Yaml::dump($config, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        $yaml = str_replace('$app_name', admin_setting('app_name', 'XBoard'), $yaml);
        return response($yaml)
            ->header('content-type', 'text/yaml')
            ->header('subscription-userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}")
            ->header('profile-update-interval', '24')
            ->header('content-disposition', 'attachment;filename*=UTF-8\'\'' . rawurlencode($appName));
    }

    /**
     * Build the rules for Clash.
     */
    public function buildRules($config)
    {
        // Force the current subscription domain to be a direct rule
        $subsDomain = request()->header('Host');
        if ($subsDomain) {
            array_unshift($config['rules'], "DOMAIN,{$subsDomain},DIRECT");
        }
        // // Force the nodes ip to be a direct rule
        // collect($this->servers)->pluck('host')->map(function ($host) {
        //     $host = trim($host);
        //     return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : Helper::getIpByDomainName($host);
        // })->flatten()->unique()->each(function ($nodeIP) use (&$config) {
        //     array_unshift($config['rules'], "IP-CIDR,{$nodeIP}/32,DIRECT,no-resolve");
        // });

        return $config;
    }

    public static function buildShadowsocks($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'ss';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['cipher'] = data_get($server['protocol_settings'], 'cipher');
        $array['password'] = data_get($server, 'password', $password);
        $array['udp'] = true;
        if (data_get($protocol_settings, 'plugin') && data_get($protocol_settings, 'plugin_opts')) {
            $plugin = data_get($protocol_settings, 'plugin');
            $pluginOpts = data_get($protocol_settings, 'plugin_opts', '');
            $array['plugin'] = $plugin;

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

            // 根据插件类型进行字段映射
            switch ($plugin) {
                case 'obfs':
                    $array['plugin-opts'] = [
                        'mode' => $parsedOpts['obfs'],
                        'host' => $parsedOpts['obfs-host'],
                    ];

                    // 可选path参数
                    if (isset($parsedOpts['path'])) {
                        $array['plugin-opts']['path'] = $parsedOpts['path'];
                    }
                    break;

                case 'v2ray-plugin':
                    $array['plugin-opts'] = [
                        'mode' => $parsedOpts['mode'] ?? 'websocket',
                        'tls' => isset($parsedOpts['tls']) && $parsedOpts['tls'] == 'true',
                        'host' => $parsedOpts['host'] ?? '',
                        'path' => $parsedOpts['path'] ?? '/',
                    ];
                    break;

                default:
                    // 对于其他插件，直接使用解析出的键值对
                    $array['plugin-opts'] = $parsedOpts;
            }
        }
        return $array;
    }

    public static function buildVmess($uuid, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'name' => $server['name'],
            'type' => 'vmess',
            'server' => $server['host'],
            'port' => $server['port'],
            'uuid' => $uuid,
            'alterId' => 0,
            'cipher' => 'auto',
            'udp' => true
        ];

        if (data_get($protocol_settings, 'tls')) {
            $array['tls'] = true;
            $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
            $array['servername'] = data_get($protocol_settings, 'tls_settings.server_name');
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                $array['network'] = data_get($protocol_settings, 'network_settings.header.type', 'tcp');
                if (data_get($protocol_settings, 'network_settings.header.type', 'none') !== 'none') {
                    $array['http-opts'] = [
                        'headers' => data_get($protocol_settings, 'network_settings.header.request.headers'),
                        'path' => data_get($protocol_settings, 'network_settings.header.request.path', ['/'])
                    ];
                }
                break;
            case 'ws':
                $array['network'] = 'ws';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $array['ws-opts']['path'] = $path;
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host'))
                    $array['ws-opts']['headers'] = ['Host' => $host];
                break;
            case 'grpc':
                $array['network'] = 'grpc';
                if ($serviceName = data_get($protocol_settings, 'network_settings.serviceName'))
                    $array['grpc-opts']['grpc-service-name'] = $serviceName;
                break;
            default:
                break;
        }

        return $array;
    }

    public static function buildVless($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'name' => $server['name'],
            'type' => 'vless',
            'server' => $server['host'],
            'port' => $server['port'],
            'uuid' => $password,
            'alterId' => 0,
            'cipher' => 'auto',
            'udp' => true,
            'flow' => data_get($protocol_settings, 'flow'),
            'tls' => false
        ];

        switch (data_get($protocol_settings, 'tls')) {
            case 1:
                $array['tls'] = true;
                $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $array['servername'] = $serverName;
                }
                break;
            case 2:
                $array['tls'] = true;
                $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'reality_settings.allow_insecure', false);
                $array['servername'] = data_get($protocol_settings, 'reality_settings.server_name');
                $array['reality-opts'] = [
                    'public-key' => data_get($protocol_settings, 'reality_settings.public_key'),
                    'short-id' => data_get($protocol_settings, 'reality_settings.short_id')
                ];
                $array['client-fingerprint'] = Helper::getRandFingerprint();
                break;
            default:
                break;
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'ws':
                $array['network'] = 'ws';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $array['ws-opts']['path'] = $path;
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host'))
                    $array['ws-opts']['headers'] = ['Host' => $host];
                break;
            case 'grpc':
                $array['network'] = 'grpc';
                if ($serviceName = data_get($protocol_settings, 'network_settings.serviceName'))
                    $array['grpc-opts']['grpc-service-name'] = $serviceName;
                break;
            default:
                break;
        }

        return $array;
    }

    public static function buildTrojan($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'name' => $server['name'],
            'type' => 'trojan',
            'server' => $server['host'],
            'port' => $server['port'],
            'password' => $password,
            'udp' => true,
            'skip-cert-verify' => (bool) data_get($protocol_settings, 'allow_insecure', false)
        ];
        if ($serverName = data_get($protocol_settings, 'server_name')) {
            $array['sni'] = $serverName;
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                $array['network'] = 'tcp';
                break;
            case 'ws':
                $array['network'] = 'ws';
                if ($path = data_get($protocol_settings, 'network_settings.path'))
                    $array['ws-opts']['path'] = $path;
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host'))
                    $array['ws-opts']['headers'] = ['Host' => $host];
                break;
            case 'grpc':
                $array['network'] = 'grpc';
                if ($serviceName = data_get($protocol_settings, 'network_settings.serviceName'))
                    $array['grpc-opts']['grpc-service-name'] = $serviceName;
                break;
            default:
                $array['network'] = 'tcp';
                break;
        }

        return $array;
    }

    public static function buildHysteria($password, $server, $user)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'name' => $server['name'],
            'server' => $server['host'],
            'port' => $server['port'],
            'sni' => data_get($protocol_settings, 'tls.server_name'),
            'up' => data_get($protocol_settings, 'bandwidth.up'),
            'down' => data_get($protocol_settings, 'bandwidth.down'),
            'skip-cert-verify' => (bool) data_get($protocol_settings, 'tls.allow_insecure', false),
        ];
        if (isset($server['ports'])) {
            $array['ports'] = $server['ports'];
        }
        switch (data_get($protocol_settings, 'version')) {
            case 1:
                $array['type'] = 'hysteria';
                $array['auth_str'] = $password;
                $array['protocol'] = 'udp'; // 支持 udp/wechat-video/faketcp
                if (data_get($protocol_settings, 'obfs.open')) {
                    $array['obfs'] = data_get($protocol_settings, 'obfs.password');
                }
                $array['fast-open'] = true;
                $array['disable_mtu_discovery'] = true;
                break;
            case 2:
                $array['type'] = 'hysteria2';
                $array['password'] = $password;
                if (data_get($protocol_settings, 'obfs.open')) {
                    $array['obfs'] = data_get($protocol_settings, 'obfs.type');
                    $array['obfs-password'] = data_get($protocol_settings, 'obfs.password');
                }
                break;
        }

        return $array;
    }

    public static function buildTuic($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'name' => $server['name'],
            'type' => 'tuic',
            'server' => $server['host'],
            'port' => $server['port'],
            'udp' => true,
        ];

        if (data_get($protocol_settings, 'version') === 4) {
            $array['token'] = $password;
        } else {
            $array['uuid'] = $password;
            $array['password'] = $password;
        }

        $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls.allow_insecure', false);
        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $array['sni'] = $serverName;
        }

        if ($alpn = data_get($protocol_settings, 'alpn')) {
            $array['alpn'] = $alpn;
        }

        $array['congestion-controller'] = data_get($protocol_settings, 'congestion_control', 'cubic');
        $array['udp-relay-mode'] = data_get($protocol_settings, 'udp_relay_mode', 'native');

        return $array;
    }

    public static function buildAnyTLS($password, $server)
    {

        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'name' => $server['name'],
            'type' => 'anytls',
            'server' => $server['host'],
            'port' => $server['port'],
            'password' => $password,
            'udp' => true,
        ];

        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $array['sni'] = $serverName;
        }
        if ($allowInsecure = data_get($protocol_settings, 'tls.allow_insecure')) {
            $array['skip-cert-verify'] = (bool) $allowInsecure;
        }

        return $array;
    }

    public static function buildMieru($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'name' => $server['name'],
            'type' => 'mieru',
            'server' => $server['host'],
            'port' => $server['port'],
            'username' => $password,
            'password' => $password,
            'transport' => strtoupper(data_get($protocol_settings, 'transport', 'TCP')),
            'multiplexing' => data_get($protocol_settings, 'multiplexing', 'MULTIPLEXING_LOW')
        ];

        // 如果配置了端口范围
        if (isset($server['ports'])) {
            $array['port-range'] = $server['ports'];
        }

        return $array;
    }

    public static function buildSocks5($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'socks5';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['udp'] = true;

        $array['username'] = $password;
        $array['password'] = $password;

        // TLS 配置
        if (data_get($protocol_settings, 'tls')) {
            $array['tls'] = true;
            $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
        }

        return $array;
    }

    public static function buildHttp($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'http';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];

        $array['username'] = $password;
        $array['password'] = $password;

        // TLS 配置
        if (data_get($protocol_settings, 'tls')) {
            $array['tls'] = true;
            $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
        }

        return $array;
    }

    private function isMatch($exp, $str)
    {
        try {
            return preg_match($exp, $str) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function isRegex($exp)
    {
        if (empty($exp)) {
            return false;
        }
        try {
            return preg_match($exp, '') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
