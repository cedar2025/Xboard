<?php

namespace App\Protocols;

use App\Models\Server;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use App\Support\AbstractProtocol;

class Clash extends AbstractProtocol
{
    public $flags = ['clash'];
    const CUSTOM_TEMPLATE_FILE = 'resources/rules/custom.clash.yaml';
    const DEFAULT_TEMPLATE_FILE = 'resources/rules/default.clash.yaml';

    public $allowedProtocols = [
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_VMESS,
        Server::TYPE_TROJAN,
        Server::TYPE_SOCKS,
        Server::TYPE_HTTP,
    ];
    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $appName = admin_setting('app_name', 'XBoard');

        // 优先从数据库配置中获取模板
        $template = subscribe_template('clash');

        $config = Yaml::parse($template);
        $proxy = [];
        $proxies = [];

        foreach ($servers as $item) {

            if (
                $item['type'] === Server::TYPE_SHADOWSOCKS
                && in_array(data_get($item['protocol_settings'], 'cipher'), [
                    'aes-128-gcm',
                    'aes-192-gcm',
                    'aes-256-gcm',
                    'chacha20-ietf-poly1305'
                ])
            ) {
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
            if ($item['type'] === Server::TYPE_SOCKS) {
                array_push($proxy, self::buildSocks5($item['password'], $item));
                array_push($proxies, $item['name']);
            }
            if ($item['type'] === Server::TYPE_HTTP) {
                array_push($proxy, self::buildHttp($item['password'], $item));
                array_push($proxies, $item['name']);
            }
        }

        $config['proxies'] = array_merge($config['proxies'] ? $config['proxies'] : [], $proxy);
        foreach ($config['proxy-groups'] as $k => $v) {
            // 自定义 FILTER:: 和 REGEX:: 前缀筛选逻辑
            $hasCustomFilter = false; // 标记是否使用了自定义筛选
            if (isset($config['proxy-groups'][$k]['proxies']) && is_array($config['proxy-groups'][$k]['proxies'])) {
                $expandedProxies = [];
                foreach ($config['proxy-groups'][$k]['proxies'] as $item) {
                    // 检查是否是 FILTER:: 前缀（字符串包含匹配）
                    if (is_string($item) && strpos($item, 'FILTER::') === 0) {
                        $hasCustomFilter = true; // 使用了自定义筛选
                        // 提取关键词
                        $keyword = substr($item, 8); // 去掉 "FILTER::" 前缀
                        if (!empty($keyword)) {
                            // 使用 mb_strpos 查找包含该关键词的所有节点
                            foreach ($proxies as $proxyName) {
                                if (mb_strpos($proxyName, $keyword) !== false) {
                                    $expandedProxies[] = $proxyName;
                                }
                            }
                        }
                    }
                    // 检查是否是 REGEX:: 前缀（正则表达式匹配）
                    elseif (is_string($item) && strpos($item, 'REGEX::') === 0) {
                        $hasCustomFilter = true; // 使用了自定义筛选
                        // 提取正则表达式模式
                        $pattern = substr($item, 7); // 去掉 "REGEX::" 前缀
                        if (!empty($pattern)) {
                            // 自动添加定界符和 UTF-8 修饰符（如果用户未提供）
                            if (strpos($pattern, '/') !== 0) {
                                $pattern = '/' . $pattern . '/u';
                            }
                            // 使用正则表达式匹配节点名称
                            foreach ($proxies as $proxyName) {
                                try {
                                    if (@preg_match($pattern, $proxyName) === 1) {
                                        $expandedProxies[] = $proxyName;
                                    }
                                } catch (\Exception $e) {
                                    // 忽略无效的正则表达式
                                }
                            }
                        }
                    } else {
                        // 普通节点名，保留原样
                        $expandedProxies[] = $item;
                    }
                }
                // 去重并更新策略组的 proxies
                $config['proxy-groups'][$k]['proxies'] = array_values(array_unique($expandedProxies));
            }

            // 如果使用了自定义筛选，跳过后续的默认节点添加逻辑
            if ($hasCustomFilter) {
                continue;
            }

            if (isset($config['proxy-groups'][$k]['filter'])) {
                $pattern = $config['proxy-groups'][$k]['filter'];
                if (strpos($pattern, '/') === false) {
                    $pattern = '/' . $pattern . '/ui';
                }
                $config['proxy-groups'][$k]['proxies'] = array_values(array_filter($proxies, function ($proxy) use ($pattern) {
                    return $this->isMatch($pattern, $proxy);
                }));
                continue;
            }

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
            ->header('content-disposition', 'attachment;filename*=UTF-8\'\'' . rawurlencode($appName))
            ->header('profile-web-page-url', admin_setting('app_url'));
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

        return $config;
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
                        'mode' => $parsedOpts['obfs'] ?? data_get($protocol_settings, 'obfs', 'http'),
                        'host' => $parsedOpts['obfs-host'] ?? data_get($protocol_settings, 'obfs_settings.host', ''),
                    ];

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

        if (data_get($protocol_settings, 'tls')) {
            $array['tls'] = true;
            $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure');
            if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                $array['servername'] = $serverName;
            }
        }

        switch (data_get($protocol_settings, 'network')) {
            case 'tcp':
                $array['network'] = data_get($protocol_settings, 'network_settings.header.type');
                if (data_get($protocol_settings, 'network_settings.header.type', 'none') !== 'none') {
                    if (
                        $httpOpts = array_filter([
                            'headers' => data_get($protocol_settings, 'network_settings.header.request.headers'),
                            'path' => data_get($protocol_settings, 'network_settings.header.request.path', ['/'])
                        ])
                    ) {
                        $array['http-opts'] = $httpOpts;
                    }
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
        if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
            $array['sni'] = $serverName;
        }
        $array['skip-cert-verify'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);

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
