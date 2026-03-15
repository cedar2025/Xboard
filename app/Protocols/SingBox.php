<?php
namespace App\Protocols;

use App\Utils\Helper;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use App\Support\AbstractProtocol;
use App\Models\Server;

class SingBox extends AbstractProtocol
{
    public $flags = ['sing-box', 'hiddify', 'sfm'];
    public $allowedProtocols = [
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_TROJAN,
        Server::TYPE_VMESS,
        Server::TYPE_VLESS,
        Server::TYPE_HYSTERIA,
        Server::TYPE_TUIC,
        Server::TYPE_ANYTLS,
        Server::TYPE_SOCKS,
        Server::TYPE_HTTP,
        Server::TYPE_MIERU,
    ];
    private $config;
    const CUSTOM_TEMPLATE_FILE = 'resources/rules/custom.sing-box.json';
    const DEFAULT_TEMPLATE_FILE = 'resources/rules/default.sing-box.json';

    /**
     * 多客户端协议支持配置
     */
    protected $protocolRequirements = [
        'sing-box' => [
            'vless' => [
                'base_version' => '1.5.0',
                'protocol_settings.flow' => [
                    'xtls-rprx-vision' => '1.5.0'
                ],
                'protocol_settings.tls' => [
                    '2' => '1.6.0' // Reality
                ]
            ],
            'hysteria' => [
                'base_version' => '1.5.0',
                'protocol_settings.version' => [
                    '2' => '1.5.0' // Hysteria 2
                ]
            ],
            'tuic' => [
                'base_version' => '1.5.0'
            ],
            'ssh' => [
                'base_version' => '1.8.0'
            ],
            'juicity' => [
                'base_version' => '1.7.0'
            ],
            'shadowtls' => [
                'base_version' => '1.6.0'
            ],
            'wireguard' => [
                'base_version' => '1.5.0'
            ],
            'anytls' => [
                'base_version' => '1.12.0'
            ],
            'mieru' => [
                'base_version' => '1.12.0'
            ]
        ]
    ];

    public function handle()
    {
        $appName = admin_setting('app_name', 'XBoard');
        $this->config = $this->loadConfig();
        $this->buildOutbounds();
        $this->buildRule();
        $this->adaptConfigForVersion();
        $user = $this->user;

        return response()
            ->json($this->config)
            ->header('profile-title', 'base64:' . base64_encode($appName))
            ->header('subscription-userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}")
            ->header('profile-update-interval', '24');
    }

    protected function loadConfig()
    {
        $jsonData = subscribe_template('singbox');

        return is_array($jsonData) ? $jsonData : json_decode($jsonData, true);
    }

    protected function buildOutbounds()
    {
        $outbounds = $this->config['outbounds'];
        $proxies = [];
        foreach ($this->servers as $item) {
            $protocol_settings = $item['protocol_settings'];
            if ($item['type'] === Server::TYPE_SHADOWSOCKS) {
                $ssConfig = $this->buildShadowsocks($item['password'], $item);
                $proxies[] = $ssConfig;
            }
            if ($item['type'] === Server::TYPE_TROJAN) {
                $trojanConfig = $this->buildTrojan($this->user['uuid'], $item);
                $proxies[] = $trojanConfig;
            }
            if ($item['type'] === Server::TYPE_VMESS) {
                $vmessConfig = $this->buildVmess($this->user['uuid'], $item);
                $proxies[] = $vmessConfig;
            }
            if (
                $item['type'] === Server::TYPE_VLESS
                && in_array(data_get($protocol_settings, 'network'), ['tcp', 'ws', 'grpc', 'http', 'quic', 'httpupgrade'])
            ) {
                $vlessConfig = $this->buildVless($this->user['uuid'], $item);
                $proxies[] = $vlessConfig;
            }
            if ($item['type'] === Server::TYPE_HYSTERIA) {
                $hysteriaConfig = $this->buildHysteria($this->user['uuid'], $item);
                $proxies[] = $hysteriaConfig;
            }
            if ($item['type'] === Server::TYPE_TUIC) {
                $tuicConfig = $this->buildTuic($this->user['uuid'], $item);
                $proxies[] = $tuicConfig;
            }
            if ($item['type'] === Server::TYPE_ANYTLS) {
                $anytlsConfig = $this->buildAnyTLS($this->user['uuid'], $item);
                $proxies[] = $anytlsConfig;
            }
            if ($item['type'] === Server::TYPE_SOCKS) {
                $socksConfig = $this->buildSocks($this->user['uuid'], $item);
                $proxies[] = $socksConfig;
            }
            if ($item['type'] === Server::TYPE_HTTP) {
                $httpConfig = $this->buildHttp($this->user['uuid'], $item);
                $proxies[] = $httpConfig;
            }
            if ($item['type'] === Server::TYPE_MIERU) {
                $mieruConfig = $this->buildMieru($this->user['uuid'], $item);
                $proxies[] = $mieruConfig;
            }
        }
        foreach ($outbounds as &$outbound) {
            if (in_array($outbound['type'], ['urltest', 'selector'])) {
                array_push($outbound['outbounds'], ...array_column($proxies, 'tag'));
            }
        }

        $outbounds = array_merge($outbounds, $proxies);
        $this->config['outbounds'] = $outbounds;
        return $outbounds;
    }

    /**
     * Build rule
     */
    protected function buildRule()
    {
        $rules = $this->config['route']['rules'];
        // Force the nodes ip to be a direct rule
        // array_unshift($rules, [
        //     'ip_cidr' => collect($this->servers)->pluck('host')->map(function ($host) {
        //         return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : Helper::getIpByDomainName($host);
        //     })->flatten()->unique()->values(),
        //     'outbound' => 'direct',
        // ]);
        $this->config['route']['rules'] = $rules;
    }

    /**
     * 根据客户端版本自适应配置格式
     * 
     * sing-box 版本断点:
     * - 1.8.0: rule_set 替代 geoip/geosite db, cache_file 替代 clash_api.cache_file
     * - 1.10.0: address 数组替代 inet4_address/inet6_address
     * - 1.11.0: 移除 endpoint_independent_nat, sniff_override_destination
     */
    protected function adaptConfigForVersion(): void
    {
        $coreVersion = $this->getSingBoxCoreVersion();
        if (empty($coreVersion)) {
            return;
        }

        // >= 1.11.0: 移除已废弃字段，避免 "配置已过时" 警告
        if (version_compare($coreVersion, '1.11.0', '>=')) {
            $this->removeDeprecatedFieldsV111();
        }

        // < 1.10.0: address 数组 → inet4_address/inet6_address
        if (version_compare($coreVersion, '1.10.0', '<')) {
            $this->convertAddressToLegacy();
        }
    }

    /**
     * 获取实际 sing-box 核心版本
     * 
     * sing-box 客户端直接报核心版本，hiddify/sfm 等 wrapper 客户端
     * 报的是 app 版本，需要映射到对应的 sing-box 核心版本
     */
    private function getSingBoxCoreVersion(): ?string
    {
        if (empty($this->clientVersion)) {
            return null;
        }

        // sing-box 原生客户端，版本即核心版本
        if ($this->clientName === 'sing-box') {
            return $this->clientVersion;
        }

        // Hiddify/SFM 等 wrapper 默认内置较新的 sing-box 核心
        // 保守策略: 直接按最新格式输出(移除废弃字段)，因为这些客户端普遍内置 >= 1.11 的核心
        return '1.11.0';
    }

    /**
     * sing-box >= 1.11.0: 移除废弃字段
     */
    private function removeDeprecatedFieldsV111(): void
    {
        if (!isset($this->config['inbounds'])) {
            return;
        }
        foreach ($this->config['inbounds'] as &$inbound) {
            unset($inbound['endpoint_independent_nat']);
            unset($inbound['sniff_override_destination']);
        }
    }

    /**
     * sing-box < 1.10.0: 将 tun address 数组转换为 inet4_address/inet6_address
     */
    private function convertAddressToLegacy(): void
    {
        if (!isset($this->config['inbounds'])) {
            return;
        }
        foreach ($this->config['inbounds'] as &$inbound) {
            if ($inbound['type'] !== 'tun' || !isset($inbound['address'])) {
                continue;
            }
            foreach ($inbound['address'] as $addr) {
                if (str_contains($addr, ':')) {
                    $inbound['inet6_address'] = $addr;
                } else {
                    $inbound['inet4_address'] = $addr;
                }
            }
            unset($inbound['address']);
        }
    }

    protected function buildShadowsocks($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings');
        $array = [];
        $array['tag'] = $server['name'];
        $array['type'] = 'shadowsocks';
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['method'] = data_get($protocol_settings, 'cipher');
        $array['password'] = data_get($server, 'password', $password);
        if (data_get($protocol_settings, 'plugin') && data_get($protocol_settings, 'plugin_opts')) {
            $array['plugin'] = data_get($protocol_settings, 'plugin');
            $array['plugin_opts'] = data_get($protocol_settings, 'plugin_opts', '');
        }

        return $array;
    }


    protected function buildVmess($uuid, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [
            'tag' => $server['name'],
            'type' => 'vmess',
            'server' => $server['host'],
            'server_port' => $server['port'],
            'uuid' => $uuid,
            'security' => 'auto',
            'alter_id' => 0,
        ];

        if ($protocol_settings['tls']) {
            $array['tls'] = [
                'enabled' => true,
                'insecure' => (bool) data_get($protocol_settings, 'tls_settings.allow_insecure'),
            ];
            if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                $array['tls']['server_name'] = $serverName;
            }
        }

        $transport = match ($protocol_settings['network']) {
            'tcp' => data_get($protocol_settings, 'network_settings.header.type', 'none') !== 'none' ? [
                'type' => 'http',
                'path' => Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/'])),
                'host' => data_get($protocol_settings, 'network_settings.header.request.headers.Host', [])
            ] : null,
            'ws' => array_filter([
                'type' => 'ws',
                'path' => data_get($protocol_settings, 'network_settings.path'),
                'headers' => ($host = data_get($protocol_settings, 'network_settings.headers.Host')) ? ['Host' => $host] : null,
                'max_early_data' => 2048,
                'early_data_header_name' => 'Sec-WebSocket-Protocol'
            ]),
            'grpc' => [
                'type' => 'grpc',
                'service_name' => data_get($protocol_settings, 'network_settings.serviceName')
            ],
            'h2' => [
                'type' => 'http',
                'host' => data_get($protocol_settings, 'network_settings.host'),
                'path' => data_get($protocol_settings, 'network_settings.path')
            ],
            'httpupgrade' => [
                'type' => 'httpupgrade',
                'path' => data_get($protocol_settings, 'network_settings.path'),
                'host' => data_get($protocol_settings, 'network_settings.host', $server['host']),
                'headers' => data_get($protocol_settings, 'network_settings.headers')
            ],
            'quic' => [
                'type' => 'quic'
            ],
            default => null
        };

        if ($transport) {
            $array['transport'] = array_filter($transport, fn($value) => !is_null($value));
        }
        return $array;
    }

    protected function buildVless($password, $server)
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            "type" => "vless",
            "tag" => $server['name'],
            "server" => $server['host'],
            "server_port" => $server['port'],
            "uuid" => $password,
            "packet_encoding" => "xudp",
            'flow' => data_get($protocol_settings, 'flow', ''),
        ];

        if ($protocol_settings['tls']) {
            $tlsConfig = [
                'enabled' => true,
                'insecure' => (bool) data_get($protocol_settings, 'tls_settings.allow_insecure'),
                'utls' => [
                    'enabled' => true,
                    'fingerprint' => Helper::getRandFingerprint()
                ]
            ];

            switch ($protocol_settings['tls']) {
                case 1:
                    if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                        $tlsConfig['server_name'] = $serverName;
                    }
                    break;
                case 2:
                    $tlsConfig['server_name'] = data_get($protocol_settings, 'reality_settings.server_name');
                    $tlsConfig['reality'] = [
                        'enabled' => true,
                        'public_key' => data_get($protocol_settings, 'reality_settings.public_key'),
                        'short_id' => data_get($protocol_settings, 'reality_settings.short_id')
                    ];
                    break;
            }

            $array['tls'] = $tlsConfig;
        }

        $transport = match ($protocol_settings['network']) {
            'tcp' => data_get($protocol_settings, 'network_settings.header.type') == 'http' ? [
                'type' => 'http',
                'path' => Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/']))
            ] : null,
            'ws' => array_filter([
                'type' => 'ws',
                'path' => data_get($protocol_settings, 'network_settings.path'),
                'headers' => ($host = data_get($protocol_settings, 'network_settings.headers.Host')) ? ['Host' => $host] : null,
                'max_early_data' => 2048,
                'early_data_header_name' => 'Sec-WebSocket-Protocol'
            ], fn($value) => !is_null($value)),
            'grpc' => [
                'type' => 'grpc',
                'service_name' => data_get($protocol_settings, 'network_settings.serviceName')
            ],
            'h2' => [
                'type' => 'http',
                'host' => data_get($protocol_settings, 'network_settings.host'),
                'path' => data_get($protocol_settings, 'network_settings.path')
            ],
            'httpupgrade' => [
                'type' => 'httpupgrade',
                'path' => data_get($protocol_settings, 'network_settings.path'),
                'host' => data_get($protocol_settings, 'network_settings.host', $server['host']),
                'headers' => data_get($protocol_settings, 'network_settings.headers')
            ],
            'quic' => [
                'type' => 'quic'
            ],
            default => null
        };

        if ($transport) {
            $array['transport'] = array_filter($transport, fn($value) => !is_null($value));
        }

        return $array;
    }

    protected function buildTrojan($password, $server)
    {
        $protocol_settings = $server['protocol_settings'];
        $array = [
            'tag' => $server['name'],
            'type' => 'trojan',
            'server' => $server['host'],
            'server_port' => $server['port'],
            'password' => $password,
            'tls' => [
                'enabled' => true,
                'insecure' => (bool) data_get($protocol_settings, 'allow_insecure', false),
            ]
        ];
        if ($serverName = data_get($protocol_settings, 'server_name')) {
            $array['tls']['server_name'] = $serverName;
        }
        $transport = match (data_get($protocol_settings, 'network')) {
            'grpc' => [
                'type' => 'grpc',
                'service_name' => data_get($protocol_settings, 'network_settings.serviceName')
            ],
            'ws' => array_filter([
                'type' => 'ws',
                'path' => data_get($protocol_settings, 'network_settings.path'),
                'headers' => data_get($protocol_settings, 'network_settings.headers.Host') ? ['Host' => [data_get($protocol_settings, 'network_settings.headers.Host')]] : null,
                'max_early_data' => 2048,
                'early_data_header_name' => 'Sec-WebSocket-Protocol'
            ]),
            default => null
        };
        if ($transport) {
            $array['transport'] = array_filter($transport, fn($value) => !is_null($value));
        }
        return $array;
    }

    protected function buildHysteria($password, $server): array
    {
        $protocol_settings = $server['protocol_settings'];
        $baseConfig = [
            'server' => $server['host'],
            'server_port' => $server['port'],
            'tag' => $server['name'],
            'tls' => [
                'enabled' => true,
                'insecure' => (bool) data_get($protocol_settings, 'tls.allow_insecure', false),
            ]
        ];
        // 支持 1.11.0 版本及以上 `server_ports` 和 `hop_interval` 配置
        if ($this->supportsFeature('sing-box', '1.11.0')) {
            if (isset($server['ports'])) {
                $baseConfig['server_ports'] = [str_replace('-', ':', $server['ports'])];
            }
            if (isset($protocol_settings['hop_interval'])) {
                $baseConfig['hop_interval'] = "{$protocol_settings['hop_interval']}s";
            }
        }

        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $baseConfig['tls']['server_name'] = $serverName;
        }
        $speedConfig = [
            'up_mbps' => data_get($protocol_settings, 'bandwidth.up'),
            'down_mbps' => data_get($protocol_settings, 'bandwidth.down'),
        ];
        $versionConfig = match (data_get($protocol_settings, 'version', 1)) {
            2 => [
                'type' => 'hysteria2',
                'password' => $password,
                'obfs' => data_get($protocol_settings, 'obfs.open') ? [
                    'type' => data_get($protocol_settings, 'obfs.type'),
                    'password' => data_get($protocol_settings, 'obfs.password')
                ] : null,
            ],
            default => [
                'type' => 'hysteria',
                'auth_str' => $password,
                'obfs' => data_get($protocol_settings, 'obfs.password'),
                'disable_mtu_discovery' => true,
            ]
        };

        return array_merge(
            $baseConfig,
            $speedConfig,
            $versionConfig
        );
    }

    protected function buildTuic($password, $server): array
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'type' => 'tuic',
            'tag' => $server['name'],
            'server' => $server['host'],
            'server_port' => $server['port'],
            'congestion_control' => data_get($protocol_settings, 'congestion_control', 'cubic'),
            'udp_relay_mode' => data_get($protocol_settings, 'udp_relay_mode', 'native'),
            'zero_rtt_handshake' => true,
            'heartbeat' => '10s',
            'tls' => [
                'enabled' => true,
                'insecure' => (bool) data_get($protocol_settings, 'tls.allow_insecure', false),
                'alpn' => data_get($protocol_settings, 'alpn', ['h3']),
            ]
        ];

        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $array['tls']['server_name'] = $serverName;
        }

        if (data_get($protocol_settings, 'version') === 4) {
            $array['token'] = $password;
        } else {
            $array['uuid'] = $password;
            $array['password'] = $password;
        }

        return $array;
    }

    protected function buildAnyTLS($password, $server): array
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'type' => 'anytls',
            'tag' => $server['name'],
            'server' => $server['host'],
            'password' => $password,
            'server_port' => $server['port'],
            'tls' => [
                'enabled' => true,
                'insecure' => (bool) data_get($protocol_settings, 'tls.allow_insecure', false),
                'alpn' => data_get($protocol_settings, 'alpn', ['h3']),
            ]
        ];

        if ($serverName = data_get($protocol_settings, 'tls.server_name')) {
            $array['tls']['server_name'] = $serverName;
        }

        return $array;
    }

    protected function buildSocks($password, $server): array
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'type' => 'socks',
            'tag' => $server['name'],
            'server' => $server['host'],
            'server_port' => $server['port'],
            'version' => '5', // 默认使用 socks5
            'username' => $password,
            'password' => $password,
        ];

        if (data_get($protocol_settings, 'udp_over_tcp')) {
            $array['udp_over_tcp'] = true;
        }

        return $array;
    }

    protected function buildHttp($password, $server): array
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'type' => 'http',
            'tag' => $server['name'],
            'server' => $server['host'],
            'server_port' => $server['port'],
            'username' => $password,
            'password' => $password,
        ];

        if ($path = data_get($protocol_settings, 'path')) {
            $array['path'] = $path;
        }

        if ($headers = data_get($protocol_settings, 'headers')) {
            $array['headers'] = $headers;
        }

        if (data_get($protocol_settings, 'tls')) {
            $array['tls'] = [
                'enabled' => true,
                'insecure' => (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false),
            ];

            if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                $array['tls']['server_name'] = $serverName;
            }
        }

        return $array;
    }

    protected function buildMieru($password, $server): array
    {
        $protocol_settings = data_get($server, 'protocol_settings', []);
        $array = [
            'type' => 'mieru',
            'tag' => $server['name'],
            'server' => $server['host'],
            'server_port' => $server['port'],
            'username' => $password,
            'password' => $password,
            'transport' => strtolower(data_get($protocol_settings, 'transport', 'tcp')),
        ];

        if (isset($server['ports'])) {
            $array['server_port_range'] = [$server['ports']];
        }

        if ($multiplexing = data_get($protocol_settings, 'multiplexing')) {
            $array['multiplexing'] = $multiplexing;
        }

        return $array;
    }
}
