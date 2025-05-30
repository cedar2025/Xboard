<?php

namespace App\Protocols;

use App\Models\ServerHysteria;
use Symfony\Component\Yaml\Yaml;
use App\Contracts\ProtocolInterface;
use App\Utils\Helper;
use Illuminate\Support\Facades\File;

class Stash implements ProtocolInterface
{
    public $flags = ['stash'];
    private $servers;
    private $user;

    const CUSTOM_TEMPLATE_FILE = 'resources/rules/custom.stash.yaml';
    const CUSTOM_CLASH_TEMPLATE_FILE = 'resources/rules/custom.clash.yaml';
    const DEFAULT_TEMPLATE_FILE = 'resources/rules/default.clash.yaml';

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
        $appName = admin_setting('app_name', 'XBoard');

        $template = File::exists(base_path(self::CUSTOM_TEMPLATE_FILE))
            ? File::get(base_path(self::CUSTOM_TEMPLATE_FILE))
            : (
                File::exists(base_path(self::CUSTOM_CLASH_TEMPLATE_FILE))
                ? File::get(base_path(self::CUSTOM_CLASH_TEMPLATE_FILE))
                : File::get(base_path(self::DEFAULT_TEMPLATE_FILE))
            );

        $config = Yaml::parse($template);
        $proxy = [];
        $proxies = [];

        foreach ($servers as $item) {
            if (
                $item['type'] === 'shadowsocks'
            ) {
                array_push($proxy, self::buildShadowsocks($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'vmess') {
                array_push($proxy, self::buildVmess($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if (
                $item['type'] === 'vless'
            ) {
                array_push($proxy, self::buildVless($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'hysteria') {
                array_push($proxy, self::buildHysteria($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'trojan') {
                array_push($proxy, self::buildTrojan($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'tuic') {
                array_push($proxy, self::buildTuic($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'socks') {
                array_push($proxy, self::buildSocks5($user['uuid'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === 'http') {
                array_push($proxy, self::buildHttp($user['uuid'], $item));
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
        // Force the current subscription domain to be a direct rule
        $subsDomain = request()->header('Host');
        if ($subsDomain) {
            array_unshift($config['rules'], "DOMAIN,{$subsDomain},DIRECT");
        }

        $yaml = Yaml::dump($config, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        $yaml = str_replace('$app_name', admin_setting('app_name', 'XBoard'), $yaml);
        return response($yaml, 200)
            ->header('subscription-userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}")
            ->header('profile-update-interval', '24')
            ->header('content-disposition', 'attachment;filename*=UTF-8\'\'' . rawurlencode($appName));
    }

    public static function buildShadowsocks($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'ss';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['cipher'] = data_get($protocol_settings, 'cipher');
        $array['password'] = $uuid;
        $array['udp'] = true;
        if (data_get($protocol_settings, 'obfs') == 'http') {
            $array['plugin'] = 'obfs';
            $array['plugin-opts'] = [
                'mode' => 'http',
                'host' => data_get($protocol_settings, 'obfs_settings.host'),
            ];
        }
        return $array;
    }

    public static function buildVmess($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'vmess';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['uuid'] = $uuid;
        $array['alterId'] = 0;
        $array['cipher'] = 'auto';
        $array['udp'] = true;

        $array['tls'] = data_get($protocol_settings, 'tls');
        $array['skip-cert-verify'] = data_get($protocol_settings, 'tls_settings.allow_insecure');
        if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
            $array['servername'] = $serverName;
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                $array['network'] = data_get($protocol_settings, 'network_settings.header.type', 'http');
                $array['http-opts']['path'] = data_get($protocol_settings, 'network_settings.header.request.path', ['/']);
                break;
            case 'ws':
                $array['network'] = 'ws';
                $array['ws-opts']['path'] = data_get($protocol_settings, 'network_settings.path');
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host')) {
                    $array['ws-opts']['headers'] = ['Host' => $host];
                }
                break;
            case 'grpc':
                $array['network'] = 'grpc';
                $array['grpc-opts'] = [];
                $array['grpc-opts']['grpc-service-name'] = data_get($protocol_settings, 'network_settings.serviceName');
                break;
            default:
                break;
        }
        return $array;
    }

    public static function buildVless($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'vless';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['uuid'] = $uuid;
        $array['flow'] = data_get($protocol_settings, 'flow');
        $array['udp'] = true;

        $array['client-fingerprint'] = Helper::getRandFingerprint();

        switch (data_get($protocol_settings, 'tls')) {
            case 1:
                $array['tls'] = true;
                $array['skip-cert-verify'] = data_get($protocol_settings, 'tls_settings.allow_insecure');
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $array['servername'] = $serverName;
                }
                break;
            case 2:
                $array['tls'] = true;
                $array['reality-opts']= [
                    'public-key' => data_get($protocol_settings, 'reality_settings.public_key'),
                    'short-id' => data_get($protocol_settings, 'reality_settings.short_id')
                ];
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                $array['network'] = data_get($protocol_settings, 'network_settings.header.type');
                $array['http-opts']['path'] = data_get($protocol_settings, 'network_settings.header.request.path', ['/'])[0];
                break;
            case 'ws':
                $array['network'] = 'ws';
                $array['ws-opts']['path'] = data_get($protocol_settings, 'network_settings.path');
                if ($host = data_get($protocol_settings, 'network_settings.headers.Host')) {
                    $array['ws-opts']['headers'] = ['Host' => $host];
                }
                break;
            case 'grpc':
                $array['network'] = 'grpc';
                $array['grpc-opts']['grpc-service-name'] = data_get($protocol_settings, 'network_settings.serviceName');
                break;
            // case 'h2':
            //     $array['network'] = 'h2';
            //     $array['h2-opts']['host'] = data_get($protocol_settings, 'network_settings.host');
            //     $array['h2-opts']['path'] = data_get($protocol_settings, 'network_settings.path');
            //     break;
        }

        return $array;
    }

    public static function buildTrojan($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [];
        $array['name'] = $server['name'];
        $array['type'] = 'trojan';
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['password'] = $password;
        $array['udp'] = true;
        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                $array['network'] = data_get($protocol_settings, 'network_settings.header.type');
                $array['http-opts']['path'] = data_get($protocol_settings, 'network_settings.header.request.path', ['/'])[0];
                break;
            case 'ws':
                $array['network'] = 'ws';
                $array['ws-opts']['path'] = data_get($protocol_settings, 'network_settings.path');
                $array['ws-opts']['headers'] = data_get($protocol_settings, 'network_settings.headers.Host') ? ['Host' => data_get($protocol_settings, 'network_settings.headers.Host')] : null;
                break;
        }
        if ($serverName = data_get($protocol_settings, 'server_name')) {
            $array['sni'] = $serverName;
        }
        $array['skip-cert-verify'] = data_get($protocol_settings, 'allow_insecure');
        return $array;
    }

    public static function buildHysteria($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array['name'] = $server['name'];
        $array['server'] = $server['host'];
        $array['port'] = $server['port'];
        $array['up-speed'] = data_get($protocol_settings, 'bandwidth.up');
        $array['down-speed'] = data_get($protocol_settings, 'bandwidth.down');
        $array['skip-cert-verify'] = data_get($protocol_settings, 'tls.allow_insecure');
        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $array['sni'] = $serverName;
        }
        if (isset($server['ports'])) {
            $array['ports'] = $server['ports'];
        }
        switch (data_get($protocol_settings, 'version')) {
            case 1:
                $array['type'] = 'hysteria';
                $array['auth-str'] = $password;
                $array['protocol'] = 'udp';
                $array['obfs'] = data_get($protocol_settings, 'obfs.open') ? data_get($protocol_settings, 'obfs.type') : null;
                break;
            case 2:
                $array['type'] = 'hysteria2';
                $array['auth'] = $password;
                $array['fast-open'] = true;
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
            'uuid' => $password,
            'password' => $password,
            'congestion-controller' => data_get($protocol_settings, 'congestion_control', 'cubic'),
            'udp-relay-mode' => data_get($protocol_settings, 'udp_relay_mode', 'native'),
            'alpn' => data_get($protocol_settings, 'alpn', ['h3']),
            'reduce-rtt' => true,
            'fast-open' => true,
            'heartbeat-interval' => 10000,
            'request-timeout' => 8000,
            'max-udp-relay-packet-size' => 1500,
        ];

        $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls.allow_insecure', false);
        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $array['sni'] = $serverName;
        }

        return $array;
    }

    public static function buildSocks5($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [
            'name' => $server['name'],
            'type' => 'socks5',
            'server' => $server['host'],
            'port' => $server['port'],
            'username' => $password,
            'password' => $password,
            'udp' => true,
        ];

        if (data_get($protocol_settings, 'tls')) {
            $array['tls'] = true;
            $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
            if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                $array['sni'] = $serverName;
            }
        }

        return $array;
    }

    public static function buildHttp($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [
            'name' => $server['name'],
            'type' => 'http',
            'server' => $server['host'],
            'port' => $server['port'],
            'username' => $password,
            'password' => $password,
        ];

        if (data_get($protocol_settings, 'tls')) {
            $array['tls'] = true;
            $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
            if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                $array['sni'] = $serverName;
            }
        }

        return $array;
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

    private function isMatch($exp, $str)
    {
        try {
            return preg_match($exp, $str);
        } catch (\Exception $e) {
            return false;
        }
    }
}
