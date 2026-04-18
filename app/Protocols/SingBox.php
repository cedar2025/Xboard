<?php
namespace App\Protocols;

use App\Utils\Helper;
use Illuminate\Support\Arr;
use App\Support\AbstractProtocol;
use App\Models\Server;
use Log;

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
                ],
                'protocol_settings.tls_settings.ech.enabled' => [
                    1 => '1.5.0'
                ],
                'protocol_settings.network' => [
                    'xhttp' => '9999.0.0'
                ]
            ],
            'vmess' => [
                'protocol_settings.tls_settings.ech.enabled' => [
                    1 => '1.5.0'
                ],
                'protocol_settings.network' => [
                    'xhttp' => '9999.0.0'
                ]
            ],
            'trojan' => [
                'protocol_settings.tls_settings.ech.enabled' => [
                    1 => '1.5.0'
                ],
                'protocol_settings.network' => [
                    'xhttp' => '9999.0.0'
                ]
            ],
            'hysteria' => [
                'base_version' => '1.5.0',
                'protocol_settings.version' => [
                    '2' => '1.5.0' // Hysteria 2
                ],
                'protocol_settings.tls.ech.enabled' => [
                    1 => '1.5.0'
                ]
            ],
            'tuic' => [
                'base_version' => '1.5.0',
                'protocol_settings.tls.ech.enabled' => [
                    1 => '1.5.0'
                ]
            ],
            'ssh' => [
                'base_version' => '1.8.0'
            ],
            'juicity' => [
                'base_version' => '1.7.0'
            ],
            'wireguard' => [
                'base_version' => '1.5.0'
            ],
            'anytls' => [
                'base_version' => '1.12.0',
                'protocol_settings.tls.ech.enabled' => [
                    1 => '1.12.0'
                ]
            ],
            'socks' => [
                'protocol_settings.tls_settings.ech.enabled' => [
                    1 => '1.5.0'
                ]
            ],
            'naive' => [
                'protocol_settings.tls_settings.ech.enabled' => [
                    1 => '1.5.0'
                ]
            ],
            'http' => [
                'protocol_settings.tls_settings.ech.enabled' => [
                    1 => '1.5.0'
                ]
            ],
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
        $this->config['route']['rules'] = $rules;
    }

    /**
     * 根据客户端版本自适应配置格式
     * 模板基准格式: 1.13.0+ (最新)
     */
    protected function adaptConfigForVersion(): void
    {
        $coreVersion = $this->getSingBoxCoreVersion();
        if (empty($coreVersion)) {
            return;
        }

        // >= 1.13.0: 移除已删除的 block/dns 出站
        if (version_compare($coreVersion, '1.13.0', '>=')) {
            $this->upgradeSpecialOutboundsToActions();
        }

        // < 1.11.0: rule action 降级为旧出站; 恢复废弃字段
        if (version_compare($coreVersion, '1.11.0', '<')) {
            $this->downgradeActionsToSpecialOutbounds();
            $this->restoreDeprecatedInboundFields();
        }

        // < 1.12.0: DNS type+server → 旧 address 格式
        if (version_compare($coreVersion, '1.12.0', '<')) {
            $this->convertDnsServersToLegacy();
        }

        // < 1.10.0: tun address 数组 → inet4_address/inet6_address
        if (version_compare($coreVersion, '1.10.0', '<')) {
            $this->convertTunAddressToLegacy();
        }
    }

    /**
     * 获取核心版本 (Hiddify/SFM 等映射到内核版本)
     */
    private function getSingBoxCoreVersion(): ?string
    {
        // 优先从 UA 提取核心版本
        if (!empty($this->userAgent)) {
            if (preg_match('/sing-box\s+v?(\d+(?:\.\d+){0,2})/i', $this->userAgent, $matches)) {
                return $matches[1];
            }
        }

        if (empty($this->clientVersion)) {
            return null;
        }

        if ($this->clientName === 'sing-box') {
            return $this->clientVersion;
        }

        return '1.13.0';
    }

    /**
     * sing-box >= 1.13.0: block/dns 出站升级为 action
     */
    private function upgradeSpecialOutboundsToActions(): void
    {
        $removedTags = [];
        $this->config['outbounds'] = array_values(array_filter(
            $this->config['outbounds'] ?? [],
            function ($outbound) use (&$removedTags) {
                if (in_array($outbound['type'] ?? '', ['block', 'dns'])) {
                    $removedTags[$outbound['tag']] = $outbound['type'];
                    return false;
                }
                return true;
            }
        ));

        if (empty($removedTags)) {
            return;
        }

        if (isset($this->config['route']['rules'])) {
            foreach ($this->config['route']['rules'] as &$rule) {
                if (!isset($rule['outbound']) || !isset($removedTags[$rule['outbound']])) {
                    continue;
                }
                $type = $removedTags[$rule['outbound']];
                unset($rule['outbound']);
                $rule['action'] = $type === 'dns' ? 'hijack-dns' : 'reject';
            }
            unset($rule);
        }
    }

    /**
     * sing-box < 1.11.0: rule action 降级为旧 block/dns 出站
     */
    private function downgradeActionsToSpecialOutbounds(): void
    {
        $needsDnsOutbound = false;
        $needsBlockOutbound = false;

        if (isset($this->config['route']['rules'])) {
            foreach ($this->config['route']['rules'] as &$rule) {
                if (!isset($rule['action'])) {
                    continue;
                }
                switch ($rule['action']) {
                    case 'hijack-dns':
                        unset($rule['action']);
                        $rule['outbound'] = 'dns-out';
                        $needsDnsOutbound = true;
                        break;
                    case 'reject':
                        unset($rule['action']);
                        $rule['outbound'] = 'block';
                        $needsBlockOutbound = true;
                        break;
                }
            }
            unset($rule);
        }

        if ($needsBlockOutbound) {
            $this->config['outbounds'][] = ['type' => 'block', 'tag' => 'block'];
        }
        if ($needsDnsOutbound) {
            $this->config['outbounds'][] = ['type' => 'dns', 'tag' => 'dns-out'];
        }
    }

    /**
     * sing-box < 1.11.0: 恢复废弃的入站字段
     */
    private function restoreDeprecatedInboundFields(): void
    {
        if (!isset($this->config['inbounds'])) {
            return;
        }
        foreach ($this->config['inbounds'] as &$inbound) {
            if ($inbound['type'] === 'tun') {
                $inbound['endpoint_independent_nat'] = true;
            }
            if (!empty($inbound['sniff'])) {
                $inbound['sniff_override_destination'] = true;
            }
        }
    }

    /**
     * sing-box < 1.12.0: 将新 DNS server type+server 格式转换为旧 address 格式
     */
    private function convertDnsServersToLegacy(): void
    {
        if (!isset($this->config['dns']['servers'])) {
            return;
        }
        foreach ($this->config['dns']['servers'] as &$server) {
            if (!isset($server['type'])) {
                continue;
            }
            $type = $server['type'];
            $host = $server['server'] ?? null;
            switch ($type) {
                case 'https':
                    $server['address'] = "https://{$host}/dns-query";
                    break;
                case 'tls':
                    $server['address'] = "tls://{$host}";
                    break;
                case 'tcp':
                    $server['address'] = "tcp://{$host}";
                    break;
                case 'quic':
                    $server['address'] = "quic://{$host}";
                    break;
                case 'udp':
                    $server['address'] = $host;
                    break;
                case 'block':
                    $server['address'] = 'rcode://refused';
                    break;
                case 'rcode':
                    $server['address'] = 'rcode://' . ($server['rcode'] ?? 'success');
                    unset($server['rcode']);
                    break;
                default:
                    $server['address'] = $host;
                    break;
            }
            unset($server['type'], $server['server']);
        }
        unset($server);
    }

    /**
     * sing-box < 1.10.0: 将 tun address 数组转换为 inet4_address/inet6_address
     */
    private function convertTunAddressToLegacy(): void
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

            $this->appendUtls($array['tls'], $protocol_settings);
            $this->appendEch($array['tls'], data_get($protocol_settings, 'tls_settings.ech'));

            if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                $array['tls']['server_name'] = $serverName;
            }
        }

        $this->appendMultiplex($array, $protocol_settings);

        if ($transport = $this->buildTransport($protocol_settings, $server)) {
            $array['transport'] = $transport;
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
        ];
        if ($flow = data_get($protocol_settings, 'flow')) {
            $array['flow'] = $flow;
        }

        if (data_get($protocol_settings, 'tls')) {
            $tlsMode = (int) data_get($protocol_settings, 'tls', 0);
            $tlsConfig = [
                'enabled' => true,
                'insecure' => $tlsMode === 2
                    ? (bool) data_get($protocol_settings, 'reality_settings.allow_insecure', false)
                    : (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false),
            ];

            $this->appendUtls($tlsConfig, $protocol_settings);

            switch ($tlsMode) {
                case 1:
                    $this->appendEch($tlsConfig, data_get($protocol_settings, 'tls_settings.ech'));
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

        $this->appendMultiplex($array, $protocol_settings);

        if ($transport = $this->buildTransport($protocol_settings, $server)) {
            $array['transport'] = $transport;
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
        ];

        $tlsMode = (int) data_get($protocol_settings, 'tls', 1);
        $tlsConfig = ['enabled' => true];

        switch ($tlsMode) {
            case 2: // Reality
                $tlsConfig['insecure'] = (bool) data_get($protocol_settings, 'reality_settings.allow_insecure', false);
                $tlsConfig['server_name'] = data_get($protocol_settings, 'reality_settings.server_name');
                $tlsConfig['reality'] = [
                    'enabled' => true,
                    'public_key' => data_get($protocol_settings, 'reality_settings.public_key'),
                    'short_id' => data_get($protocol_settings, 'reality_settings.short_id'),
                ];
                break;
            default: // Standard TLS
                $tlsConfig['insecure'] = (bool) data_get($protocol_settings, 'tls_settings.allow_insecure', false);
                $this->appendEch($tlsConfig, data_get($protocol_settings, 'tls_settings.ech'));
                if ($serverName = data_get($protocol_settings, 'tls_settings.server_name')) {
                    $tlsConfig['server_name'] = $serverName;
                }
                break;
        }

        $this->appendUtls($tlsConfig, $protocol_settings);
        $array['tls'] = $tlsConfig;

        $this->appendMultiplex($array, $protocol_settings);

        if ($transport = $this->buildTransport($protocol_settings, $server)) {
            $array['transport'] = $transport;
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
        $this->appendEch($baseConfig['tls'], data_get($protocol_settings, 'tls.ech'));
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

        return array_filter(
            array_merge($baseConfig, $speedConfig, $versionConfig),
            fn($v) => !is_null($v)
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
        $this->appendEch($array['tls'], data_get($protocol_settings, 'tls.ech'));

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
        $this->appendEch($array['tls'], data_get($protocol_settings, 'tls.ech'));

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
            $this->appendEch($array['tls'], data_get($protocol_settings, 'tls_settings.ech'));
        }

        return $array;
    }

    protected function buildTransport(array $protocol_settings, array $server): ?array
    {
        $transport = match (data_get($protocol_settings, 'network')) {
            'tcp' => data_get($protocol_settings, 'network_settings.header.type') === 'http' ? [
                'type' => 'http',
                'path' => Arr::random(data_get($protocol_settings, 'network_settings.header.request.path', ['/'])),
                'host' => data_get($protocol_settings, 'network_settings.header.request.headers.Host', [])
            ] : null,
            'ws' => [
                'type' => 'ws',
                'path' => data_get($protocol_settings, 'network_settings.path'),
                'headers' => ($host = data_get($protocol_settings, 'network_settings.headers.Host')) ? ['Host' => $host] : null,
                'max_early_data' => 0,
                // 'early_data_header_name' => 'Sec-WebSocket-Protocol'
            ],
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
            'quic' => ['type' => 'quic'],
            default => null
        };

        if (!$transport) {
            return null;
        }

        return array_filter($transport, fn($v) => !is_null($v));
    }

    protected function appendMultiplex(&$array, $protocol_settings)
    {
        if ($multiplex = data_get($protocol_settings, 'multiplex')) {
            if (data_get($multiplex, 'enabled')) {
                $array['multiplex'] = [
                    'enabled' => true,
                    'protocol' => data_get($multiplex, 'protocol', 'yamux'),
                    'max_connections' => data_get($multiplex, 'max_connections'),
                    'min_streams' => data_get($multiplex, 'min_streams'),
                    'max_streams' => data_get($multiplex, 'max_streams'),
                    'padding' => (bool) data_get($multiplex, 'padding', false),
                ];
                if (data_get($multiplex, 'brutal.enabled')) {
                    $array['multiplex']['brutal'] = [
                        'enabled' => true,
                        'up_mbps' => data_get($multiplex, 'brutal.up_mbps'),
                        'down_mbps' => data_get($multiplex, 'brutal.down_mbps'),
                    ];
                }
                $array['multiplex'] = array_filter($array['multiplex'], fn($v) => !is_null($v));
            }
        }
    }

    protected function appendUtls(&$tlsConfig, $protocol_settings)
    {
        if ($utls = data_get($protocol_settings, 'utls')) {
            if (data_get($utls, 'enabled')) {
                $tlsConfig['utls'] = [
                    'enabled' => true,
                    'fingerprint' => Helper::getTlsFingerprint($utls)
                ];
            }
        }
    }

    protected function appendEch(&$tlsConfig, $ech): void
    {
        if ($normalized = Helper::normalizeEchSettings($ech)) {
            // Client outbound only needs the public ECH config, not the server's private key
            $tlsConfig['ech'] = array_filter([
                'enabled' => true,
                'config' => data_get($normalized, 'config') ? [data_get($normalized, 'config')] : null,
                'query_server_name' => data_get($normalized, 'query_server_name'),
            ], fn($value) => $value !== null);
        }
    }
}
