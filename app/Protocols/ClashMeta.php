<?php

namespace App\Protocols;

use App\Contracts\ProtocolInterface;
use App\Models\ServerHysteria;
use App\Utils\Helper;
use Symfony\Component\Yaml\Yaml;

class ClashMeta implements ProtocolInterface
{
    public $flags = ['meta', 'verge', 'flclash'];
    private $servers;
    private $user;

    public function __construct($user, $servers, array $options = null)
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
        $appName = admin_setting('app_name', 'XBoard');
        $defaultConfig = base_path() . '/resources/rules/default.clash.yaml';
        $customClashConfig = base_path() . '/resources/rules/custom.clash.yaml';
        $customConfig = base_path() . '/resources/rules/custom.clashmeta.yaml';
        if (\File::exists($customConfig)) {
            $config = Yaml::parseFile($customConfig);
        } elseif (\File::exists($customClashConfig)) {
            $config = Yaml::parseFile($customClashConfig);
        } else {
            $config = Yaml::parseFile($defaultConfig);
        }
        $proxy = [];
        $proxies = [];

        foreach ($servers as $item) {
            if ($item['type'] === 'shadowsocks') {
                array_push($proxy, self::buildShadowsocks($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'vmess') {
                array_push($proxy, self::buildVmess($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'trojan') {
                array_push($proxy, self::buildTrojan($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'vless') {
                array_push($proxy, self::buildVless($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'hysteria') {
                array_push($proxy, self::buildHysteria($user['uuid'], $item, $user));
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
        return response($yaml, 200)
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
        // Force the nodes ip to be a direct rule
        collect($this->servers)->pluck('host')->map(function ($host) {
            $host = trim($host);
            return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : Helper::getIpByDomainName($host);
        })->flatten()->unique()->each(function ($nodeIP) use (&$config) {
            array_unshift($config['rules'], "IP-CIDR,{$nodeIP}/32,DIRECT,no-resolve");
        });

        return $config;
    }

    public static function buildShadowsocks($password, $server)
    {
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'ss';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['cipher'] = data_get($server['protocol_settings'], 'cipher');
        $array['password'] = data_get($server, 'password', $password);
        $array['udp'] = true;
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
                        'path' => \Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/']))
                    ];
                }
                break;
            case 'ws':
                $array['network'] = 'ws';
                $array['ws-opts'] = [
                    'path' => data_get($protocol_settings, 'network_settings.path'),
                    'headers' => ['Host' => data_get($protocol_settings, 'network_settings.headers.Host', $server['host'])]
                ];
                break;
            case 'grpc':
                $array['network'] = 'grpc';
                $array['grpc-opts'] = [
                    'grpc-service-name' => data_get($protocol_settings, 'network_settings.serviceName')
                ];
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
            'flow' => data_get($protocol_settings, 'flow')
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
                $array['ws-opts']['path'] = data_get($protocol_settings, 'network_settings.path', '/');
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host')) {
                    $array['ws-opts']['headers'] = ['Host' => $host];
                }
                break;
            case 'grpc':
                $array['network'] = 'grpc';
                $array['grpc-opts'] = [
                    'grpc-service-name' => data_get($protocol_settings, 'network_settings.serviceName')
                ];
                break;
            case 'h2':
                $array['network'] = 'h2';
                $array['h2-opts'] = [
                    'path' => data_get($protocol_settings, 'network_settings.path', '/'),
                    'host' => data_get($protocol_settings, 'network_settings.host')
                ];
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
            case 'grpc':
                $array['network'] = 'grpc';
                $array['grpc-opts'] = [
                    'grpc-service-name' => data_get($protocol_settings, 'network_settings.serviceName')
                ];
                break;
            case 'ws':
                $array['network'] = 'ws';
                $array['ws-opts']['path'] = data_get($protocol_settings, 'network_settings.path', '/');
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host')) {
                    $array['ws-opts']['headers'] = ['Host' => $host];
                }
                break;
            default:
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

    private function isMatch($exp, $str)
    {
        return @preg_match($exp, $str);
    }

    private function isRegex($exp)
    {
        if (empty($exp)) {
            return false;
        }
        return @preg_match($exp, '') !== false;
    }
}
