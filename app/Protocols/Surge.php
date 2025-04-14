<?php

namespace App\Protocols;

use App\Utils\Helper;
use App\Contracts\ProtocolInterface;

class Surge implements ProtocolInterface
{
    public $flags = ['surge'];
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

        $appName = admin_setting('app_name', 'XBoard');

        $proxies = '';
        $proxyGroup = '';

        foreach ($servers as $item) {
            if (
                $item['type'] === 'shadowsocks'
                && in_array(data_get($item, 'protocol_settings.cipher'), [
                    'aes-128-gcm',
                    'aes-192-gcm',
                    'aes-256-gcm',
                    'chacha20-ietf-poly1305'
                ])
            ) {
                $proxies .= self::buildShadowsocks($item['password'], $item);
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'vmess') {
                $proxies .= self::buildVmess($user['uuid'], $item);
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'trojan') {
                $proxies .= self::buildTrojan($user['uuid'], $item);
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'hysteria') {
                $proxies .= self::buildHysteria($user['uuid'], $item);
                $proxyGroup .= $item['name'] . ', ';
            }
        }

        // 优先从 admin_setting 获取模板
        $config = admin_setting('subscribe_template_surge');
        if (empty($config)) {
            $defaultConfig = base_path('resources/rules/default.surge.conf');
            $customConfig = base_path('resources/rules/custom.surge.conf');
            if (file_exists($customConfig)) {
                $config = file_get_contents($customConfig);
            } else {
                $config = file_get_contents($defaultConfig);
            }
        }

        // Subscription link
        $subsDomain = request()->header('Host');
        $subsURL = Helper::getSubscribeUrl($user['token'], $subsDomain ? 'https://' . $subsDomain : null);

        $config = str_replace('$subs_link', $subsURL, $config);
        $config = str_replace('$subs_domain', $subsDomain, $config);
        $config = str_replace('$proxies', $proxies, $config);
        $config = str_replace('$proxy_group', rtrim($proxyGroup, ', '), $config);

        $upload = round($user['u'] / (1024 * 1024 * 1024), 2);
        $download = round($user['d'] / (1024 * 1024 * 1024), 2);
        $useTraffic = $upload + $download;
        $totalTraffic = round($user['transfer_enable'] / (1024 * 1024 * 1024), 2);
        $unusedTraffic = $totalTraffic - $useTraffic;
        $expireDate = $user['expired_at'] === NULL ? '长期有效' : date('Y-m-d H:i:s', $user['expired_at']);
        $subscribeInfo = "title={$appName}订阅信息, content=上传流量：{$upload}GB\\n下载流量：{$download}GB\\n剩余流量：{ $unusedTraffic }GB\\n套餐流量：{$totalTraffic}GB\\n到期时间：{$expireDate}";
        $config = str_replace('$subscribe_info', $subscribeInfo, $config);

        return response($config, 200)
            ->header('content-disposition', "attachment;filename*=UTF-8''" . rawurlencode($appName) . ".conf");
    }


    public static function buildShadowsocks($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $config = [
            "{$server['name']}=ss",
            "{$server['host']}",
            "{$server['port']}",
            "encrypt-method={$protocol_settings['cipher']}",
            "password={$password}",
            'tfo=true',
            'udp-relay=true'
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
            "username={$uuid}",
            "vmess-aead=true",
            'tfo=true',
            'udp-relay=true'
        ];

        if (data_get($protocol_settings, 'tls')) {
            array_push($config, 'tls=true');
            if (data_get($protocol_settings, 'tls_settings')) {
                $tlsSettings = data_get($protocol_settings, 'tls_settings');
                if (data_get($tlsSettings, 'allow_insecure'))
                    array_push($config, 'skip-cert-verify=' . ($tlsSettings['allow_insecure'] ? 'true' : 'false'));
                if (data_get($tlsSettings, 'server_name'))
                    array_push($config, "sni={$tlsSettings['server_name']}");
            }
        }
        if (data_get($protocol_settings, 'network') === 'ws') {
            array_push($config, 'ws=true');
            if (data_get($protocol_settings, 'network_settings')) {
                $wsSettings = data_get($protocol_settings, 'network_settings');
                if (data_get($wsSettings, 'path'))
                    array_push($config, "ws-path={$wsSettings['path']}");
                if (data_get($wsSettings, 'headers.Host'))
                    array_push($config, "ws-headers=Host:{$wsSettings['headers']['Host']}");
            }
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
            "password={$password}",
            $protocol_settings['server_name'] ? "sni={$protocol_settings['server_name']}" : "",
            'tfo=true',
            'udp-relay=true'
        ];
        if (!empty($protocol_settings['allow_insecure'])) {
            array_push($config, $protocol_settings['allow_insecure'] ? 'skip-cert-verify=true' : 'skip-cert-verify=false');
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    //参考文档: https://manual.nssurge.com/policy/proxy.html
    public static function buildHysteria($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        if ($protocol_settings['version'] != 2) {
            return '';
        }

        $config = [
            "{$server['name']}=hysteria2",
            "{$server['host']}",
            "{$server['port']}",
            "password={$password}",
        ];

        // 可选字段：download-bandwidth（非必须）
        $bandwidthDown = data_get($protocol_settings, 'bandwidth.down');
        if (!empty($bandwidthDown)) {
            $config[] = "download-bandwidth={$bandwidthDown}";
        }

        // 可选字段：sni
        $sni = data_get($protocol_settings, 'tls.server_name');
        if (!empty($sni)) {
            $config[] = "sni={$sni}";
        }

        // 可选字段：跳过证书验证
        $skipVerify = data_get($protocol_settings, 'tls.allow_insecure');
        if (isset($skipVerify)) {
            $config[] = $skipVerify ? 'skip-cert-verify=true' : 'skip-cert-verify=false';
        }
        $config[] = 'udp-relay=true';
        $config = array_filter($config);
        $uri = implode(',', $config) . "\r\n";
        return $uri;
    }
}
