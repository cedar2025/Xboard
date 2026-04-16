<?php


namespace App\Http\Requests\Admin;

use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;

class ServerSave extends FormRequest
{
    private const UTLS_RULES = [
        'utls.enabled' => 'nullable|boolean',
        'utls.fingerprint' => 'nullable|string',
    ];

    private const MULTIPLEX_RULES = [
        'multiplex.enabled' => 'nullable|boolean',
        'multiplex.protocol' => 'nullable|string',
        'multiplex.max_connections' => 'nullable|integer',
        'multiplex.min_streams' => 'nullable|integer',
        'multiplex.max_streams' => 'nullable|integer',
        'multiplex.padding' => 'nullable|boolean',
        'multiplex.brutal.enabled' => 'nullable|boolean',
        'multiplex.brutal.up_mbps' => 'nullable|integer',
        'multiplex.brutal.down_mbps' => 'nullable|integer',
    ];

    private const ECH_RULES = [
        'enabled' => 'nullable|boolean',
        'config' => 'nullable|string',
        'query_server_name' => 'nullable|string',
        'key' => 'nullable|string',
    ];

    private const REALITY_RULES = [
        'reality_settings.allow_insecure' => 'nullable|boolean',
        'reality_settings.server_name' => 'nullable|string',
        'reality_settings.server_port' => 'nullable|integer',
        'reality_settings.public_key' => 'nullable|string',
        'reality_settings.private_key' => 'nullable|string',
        'reality_settings.short_id' => 'nullable|string',
    ];

    private const PROTOCOL_RULES = [
        'shadowsocks' => [
            'cipher' => 'required|string',
            'obfs' => 'nullable|string',
            'obfs_settings.path' => 'nullable|string',
            'obfs_settings.host' => 'nullable|string',
            'plugin' => 'nullable|string',
            'plugin_opts' => 'nullable|string',
        ],
        'vmess' => [
            'tls' => 'required|integer',
            'network' => 'required|string',
            'network_settings' => 'nullable|array',
        ],
        'trojan' => [
            'tls' => 'nullable|integer',
            'network' => 'required|string',
            'network_settings' => 'nullable|array',
            'server_name' => 'nullable|string',
            'allow_insecure' => 'nullable|boolean',
        ],
        'hysteria' => [
            'version' => 'required|integer',
            'alpn' => 'nullable|string',
            'obfs.open' => 'nullable|boolean',
            'obfs.type' => 'string|nullable',
            'obfs.password' => 'string|nullable',
            'bandwidth.up' => 'nullable|integer',
            'bandwidth.down' => 'nullable|integer',
            'hop_interval' => 'integer|nullable',
        ],
        'vless' => [
            'tls' => 'required|integer',
            'network' => 'required|string',
            'network_settings' => 'nullable|array',
            'flow' => 'nullable|string',
            'encryption' => 'nullable|array',
            'encryption.enabled' => 'nullable|boolean',
            'encryption.encryption' => 'nullable|string',
            'encryption.decryption' => 'nullable|string',
        ],
        'socks' => [
            'tls' => 'nullable|integer',
        ],
        'naive' => [
            'tls' => 'required|integer',
        ],
        'http' => [
            'tls' => 'required|integer',
        ],
        'mieru' => [
            'transport' => 'required|string|in:TCP,UDP',
            'traffic_pattern' => 'string',
        ],
        'anytls' => [
            'tls' => 'nullable|array',
            'alpn' => 'nullable|string',
            'padding_scheme' => 'nullable|array',
        ],
    ];

    private function getBaseRules(): array
    {
        return [
            'type' => 'required|in:' . implode(',', Server::VALID_TYPES),
            'spectific_key' => 'nullable|string',
            'code' => 'nullable|string',
            'show' => '',
            'name' => 'required|string',
            'group_ids' => 'nullable|array',
            'route_ids' => 'nullable|array',
            'parent_id' => 'nullable|integer',
            'machine_id' => 'nullable|integer',
            'enabled' => 'nullable|boolean',
            'host' => 'required',
            'port' => 'required',
            'server_port' => 'required',
            'tags' => 'nullable|array',
            'excludes' => 'nullable|array',
            'ips' => 'nullable|array',
            'rate' => 'required|numeric',
            'rate_time_enable' => 'nullable|boolean',
            'rate_time_ranges' => 'nullable|array',
            'custom_outbounds' => 'nullable|array',
            'custom_routes' => 'nullable|array',
            'cert_config' => 'nullable|array',
            'rate_time_ranges.*.start' => 'required_with:rate_time_ranges|string|date_format:H:i',
            'rate_time_ranges.*.end' => 'required_with:rate_time_ranges|string|date_format:H:i',
            'rate_time_ranges.*.rate' => 'required_with:rate_time_ranges|numeric|min:0',
            'protocol_settings' => 'array',
            'transfer_enable' => 'nullable|integer|min:0',
        ];
    }

    private function getProtocolRules(string $type): array
    {
        $rules = self::PROTOCOL_RULES[$type] ?? [];

        return match ($type) {
            'vmess' => array_merge(
                $rules,
                $this->buildTlsSettingsRules(),
                self::MULTIPLEX_RULES,
                self::UTLS_RULES,
            ),
            'trojan' => array_merge(
                $rules,
                $this->buildTlsSettingsRules(includeRoot: true),
                self::REALITY_RULES,
                self::MULTIPLEX_RULES,
                self::UTLS_RULES,
            ),
            'hysteria' => array_merge(
                $rules,
                $this->buildTlsObjectRules(),
            ),
            'tuic' => array_merge(
                $rules,
                $this->buildTlsObjectRules(),
            ),
            'vless' => array_merge(
                $rules,
                $this->buildTlsSettingsRules(),
                self::REALITY_RULES,
                self::MULTIPLEX_RULES,
                self::UTLS_RULES,
            ),
            'socks', 'naive', 'http' => array_merge(
                $rules,
                $this->buildTlsSettingsRules(includeRoot: $type !== 'socks'),
            ),
            'anytls' => array_merge(
                $rules,
                $this->buildTlsObjectRules(includeRoot: true),
            ),
            default => $rules,
        };
    }

    private function buildTlsSettingsRules(bool $includeRoot = false): array
    {
        return array_merge(
            $includeRoot ? ['tls_settings' => 'nullable|array'] : [],
            [
                'tls_settings.server_name' => 'nullable|string',
                'tls_settings.allow_insecure' => 'nullable|boolean',
                'tls_settings.ech' => 'nullable|array',
            ],
            $this->prefixRules('tls_settings.ech.', self::ECH_RULES),
        );
    }

    private function buildTlsObjectRules(bool $includeRoot = false): array
    {
        return array_merge(
            $includeRoot ? ['tls' => 'nullable|array'] : [],
            [
                'tls.server_name' => 'nullable|string',
                'tls.allow_insecure' => 'nullable|boolean',
                'tls.ech' => 'nullable|array',
            ],
            $this->prefixRules('tls.ech.', self::ECH_RULES),
        );
    }

    private function prefixRules(string $prefix, array $rules): array
    {
        $result = [];
        foreach ($rules as $field => $rule) {
            $result[$prefix . $field] = $rule;
        }
        return $result;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $rules = $this->getBaseRules();
        $protocolRules = $this->getProtocolRules($type);

        foreach ($protocolRules as $field => $rule) {
            $rules['protocol_settings.' . $field] = $rule;
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'protocol_settings.cipher' => '加密方式',
            'protocol_settings.obfs' => '混淆类型',
            'protocol_settings.network' => '传输协议',
            'protocol_settings.port_range' => '端口范围',
            'protocol_settings.traffic_pattern' => 'Traffic Pattern',
            'protocol_settings.transport' => '传输方式',
            'protocol_settings.version' => '协议版本',
            'protocol_settings.password' => '密码',
            'protocol_settings.handshake.server' => '握手服务器',
            'protocol_settings.handshake.server_port' => '握手端口',
            'protocol_settings.multiplex.enabled' => '多路复用',
            'protocol_settings.multiplex.protocol' => '复用协议',
            'protocol_settings.multiplex.max_connections' => '最大连接数',
            'protocol_settings.multiplex.min_streams' => '最小流数',
            'protocol_settings.multiplex.max_streams' => '最大流数',
            'protocol_settings.multiplex.padding' => '复用填充',
            'protocol_settings.multiplex.brutal.enabled' => 'Brutal加速',
            'protocol_settings.multiplex.brutal.up_mbps' => 'Brutal上行速率',
            'protocol_settings.multiplex.brutal.down_mbps' => 'Brutal下行速率',
            'protocol_settings.utls.enabled' => 'uTLS',
            'protocol_settings.utls.fingerprint' => 'uTLS指纹',
            'protocol_settings.tls_settings.ech.enabled' => 'ECH',
            'protocol_settings.tls_settings.ech.config' => 'ECH配置',
            'protocol_settings.tls_settings.ech.query_server_name' => 'ECH查询域名',
            'protocol_settings.tls_settings.ech.key' => 'ECH密钥',
            'protocol_settings.tls.ech.enabled' => 'ECH',
            'protocol_settings.tls.ech.config' => 'ECH配置',
            'protocol_settings.tls.ech.query_server_name' => 'ECH查询域名',
            'protocol_settings.tls.ech.key' => 'ECH密钥',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '节点名称不能为空',
            'group_ids.required' => '权限组不能为空',
            'group_ids.array' => '权限组格式不正确',
            'route_ids.array' => '路由组格式不正确',
            'parent_id.integer' => '父ID格式不正确',
            'host.required' => '节点地址不能为空',
            'port.required' => '连接端口不能为空',
            'server_port.required' => '后端服务端口不能为空',
            'tls.required' => 'TLS不能为空',
            'tags.array' => '标签格式不正确',
            'rate.required' => '倍率不能为空',
            'rate.numeric' => '倍率格式不正确',
            'network.required' => '传输协议不能为空',
            'network.in' => '传输协议格式不正确',
            'networkSettings.array' => '传输协议配置有误',
            'ruleSettings.array' => '规则配置有误',
            'tlsSettings.array' => 'tls配置有误',
            'dnsSettings.array' => 'dns配置有误',
            'protocol_settings.*.required' => ':attribute 不能为空',
            'protocol_settings.*.required_if' => ':attribute 不能为空',
            'protocol_settings.*.string' => ':attribute 必须是字符串',
            'protocol_settings.*.integer' => ':attribute 必须是整数',
            'protocol_settings.*.in' => ':attribute 的值不合法',
            'transfer_enable.integer' => '流量上限必须是整数',
            'transfer_enable.min' => '流量上限不能小于0',
        ];
    }
}
