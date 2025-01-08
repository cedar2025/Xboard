<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ServerSave extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $type = $this->input('type');
        $protocolRules = [
            'type' => 'required|in:shadowsocks,vmess,trojan,hysteria,vless',
            'spectific_key' => 'nullable|string',
            'code' => 'nullable|string',
            'show' => '',
            'name' => 'required|string',
            'group_ids' => 'nullable|array',
            'route_ids' => 'nullable|array',
            'parent_id' => 'nullable|integer',
            'host' => 'required',
            'port' => 'required',
            'server_port' => 'required',
            'tags' => 'nullable|array',
            'excludes' => 'nullable|array',
            'ips' => 'nullable|array',
            'rate' => 'required|numeric',
            'protocol_settings' => 'array',
        ];

        $protocolSpecificRules = [
            'shadowsocks' => [
                'cipher' => 'required|string',
                'obfs' => 'nullable|string',
                'obfs_settings.path' => 'nullable|string',
                'obfs_settings.host' => 'nullable|string',
            ],
            'vmess' => [
                'tls' => 'required|integer',
                'network' => 'required|string',
                'network_settings' => 'nullable|array',
                'tls_settings.server_name' => 'nullable|string',
                'tls_settings.allow_insecure' => 'nullable|boolean',
            ],
            'trojan' => [
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
                'tls.server_name' => 'nullable|string',
                'tls.allow_insecure' => 'nullable|boolean',
                'bandwidth.up' => 'nullable|numeric',
                'bandwidth.down' => 'nullable|numeric',
            ],
            'vless' => [
                'tls' => 'required|integer',
                'network' => 'required|string',
                'network_settings' => 'nullable|array',
                'flow' => 'nullable|string',
                'tls_settings.server_name' => 'nullable|string',
                'tls_settings.allow_insecure' => 'nullable|boolean',
                'reality_settings.allow_insecure' => 'nullable|boolean',
                'reality_settings.server_name' => 'nullable|string',
                'reality_settings.server_port' => 'nullable|string',
                'reality_settings.public_key' => 'nullable|string',
                'reality_settings.private_key' => 'nullable|string',
                'reality_settings.short_id' => 'nullable|string',
            ]
        ];

        $rules = $protocolRules;
        foreach ($protocolSpecificRules[$type] as $field => $rule) {
            $rules['protocol_settings.' . $field] = $rule;
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => '节点名称不能为空',
            'group_id.required' => '权限组不能为空',
            'group_id.array' => '权限组格式不正确',
            'route_id.array' => '路由组格式不正确',
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
            'dnsSettings.array' => 'dns配置有误'
        ];
    }
}
