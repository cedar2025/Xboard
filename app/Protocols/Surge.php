<?php

namespace App\Protocols;

use App\Utils\Helper;
use Illuminate\Support\Facades\File;
use App\Support\AbstractProtocol;

class Surge extends AbstractProtocol
{
    public $flags = ['surge'];
    const CUSTOM_TEMPLATE_FILE = 'resources/rules/custom.surge.conf';
    const DEFAULT_TEMPLATE_FILE = 'resources/rules/default.surge.conf';

    protected $protocolRequirements = [
        'surge' => [
            'hysteria' => [
                'protocol_settings.version' => [
                    '2' => '2398'
                ],
            ],
        ],
    ];

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
                $proxies .= self::buildVmess($item['password'], $item);
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'trojan') {
                $proxies .= self::buildTrojan($item['password'], $item);
                $proxyGroup .= $item['name'] . ', ';
            }
            if ($item['type'] === 'hysteria') {
                $proxies .= self::buildHysteria($item['password'], $item);
                $proxyGroup .= $item['name'] . ', ';
            }
        }


        $config = admin_setting('subscribe_template_surge', File::exists(base_path(self::CUSTOM_TEMPLATE_FILE))
            ? File::get(base_path(self::CUSTOM_TEMPLATE_FILE))
            : File::get(base_path(self::DEFAULT_TEMPLATE_FILE)));

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
            ->header('content-type', 'application/octet-stream')
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
            array_push($config, !!data_get($protocol_settings, 'allow_insecure') ? 'skip-cert-verify=true' : 'skip-cert-verify=false');
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
        if ($protocol_settings['version'] != 2)
            return '';
        $config = [
            "{$server['name']}=hysteria2",
            "{$server['host']}",
            "{$server['port']}",
            "password={$password}",
            $protocol_settings['tls']['server_name'] ? "sni={$protocol_settings['tls']['server_name']}" : "",
            // 'tfo=true', 
            'udp-relay=true'
        ];
        if (data_get($protocol_settings, 'bandwidth.up')) {
            $config[] = "upload-bandwidth={$protocol_settings['bandwidth']['up']}";
        }
        if (data_get($protocol_settings, 'bandwidth.down')) {
            $config[] = "download-bandwidth={$protocol_settings['bandwidth']['down']}";
        }
        if (data_get($protocol_settings, 'tls.allow_insecure')) {
            $config[] = !!data_get($protocol_settings, 'tls.allow_insecure') ? 'skip-cert-verify=true' : 'skip-cert-verify=false';
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }
}
