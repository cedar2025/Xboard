<?php

namespace Tests\Feature\Server;

use App\Protocols\Shadowrocket;
use Tests\TestCase;

class ShadowrocketVlessUriTest extends TestCase
{
    private const UUID = '00000000-0000-0000-0000-000000000001';

    public function test_vless_encryption_tls_xhttp_uri_uses_standard_format(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'name' => 'xhttp node',
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => [
                    'server_name' => 'example.com',
                    'allow_insecure' => false,
                ],
                'utls' => ['enabled' => true, 'fingerprint' => 'chrome'],
                'flow' => 'xtls-rprx-vision',
                'encryption' => [
                    'enabled' => true,
                    'encryption' => 'test-client-encryption',
                    'decryption' => 'test-server-decryption',
                ],
                'network' => 'xhttp',
                'network_settings' => [
                    'path' => '/test-path',
                    'host' => 'example.com',
                    'mode' => 'auto',
                ],
            ],
        ]));

        $this->assertStringStartsWith('vless://' . self::UUID . '@', $uri);
        $this->assertStringContainsString('tfo=1', $uri);
        $this->assertStringContainsString('encryption=test-client-encryption', $uri);
        $this->assertStringContainsString('security=tls', $uri);
        $this->assertStringContainsString('sni=example.com', $uri);
        $this->assertStringContainsString('flow=xtls-rprx-vision', $uri);
        $this->assertStringContainsString('type=xhttp', $uri);
        $this->assertStringContainsString('path=%2Ftest-path', $uri);
        $this->assertStringContainsString('host=example.com', $uri);
        $this->assertStringContainsString('mode=auto', $uri);

        $this->assertStringNotContainsString('BASE64(auto:', $uri);
        $this->assertStringNotContainsString(
            base64_encode('auto:' . self::UUID . '@example.com:443'),
            $uri
        );
        $this->assertStringNotContainsString('test-server-decryption', $uri);
        $this->assertStringNotContainsString('remark=', $uri);
        $this->assertStringNotContainsString('obfs=xhttp', $uri);
    }

    public function test_vless_encryption_disabled_outputs_none(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'encryption' => [
                    'enabled' => false,
                    'encryption' => 'test-client-encryption',
                    'decryption' => 'test-server-decryption',
                ],
                'network' => 'tcp',
            ],
        ]));

        $this->assertStringContainsString('encryption=none', $uri);
        $this->assertStringNotContainsString('test-client-encryption', $uri);
        $this->assertStringNotContainsString('test-server-decryption', $uri);
    }

    public function test_vless_encryption_enabled_without_client_value_falls_back_to_none(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'encryption' => [
                    'enabled' => true,
                    'encryption' => null,
                    'decryption' => 'test-server-decryption',
                ],
                'network' => 'tcp',
            ],
        ]));

        $this->assertStringContainsString('encryption=none', $uri);
        $this->assertStringNotContainsString('test-server-decryption', $uri);
    }

    public function test_vless_reality_uri(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 2,
                'utls' => ['enabled' => true, 'fingerprint' => 'chrome'],
                'reality_settings' => [
                    'server_name' => 'example.com',
                    'public_key' => 'test-public-key',
                    'short_id' => 'test-short-id',
                ],
                'network' => 'tcp',
            ],
        ]));

        $this->assertStringStartsWith('vless://' . self::UUID . '@', $uri);
        $this->assertStringContainsString('security=reality', $uri);
        $this->assertStringContainsString('sni=example.com', $uri);
        $this->assertStringContainsString('pbk=test-public-key', $uri);
        $this->assertStringContainsString('sid=test-short-id', $uri);
        $this->assertStringContainsString('fp=chrome', $uri);
        $this->assertStringContainsString('encryption=none', $uri);
    }

    public function test_vless_ws_uri(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'network' => 'ws',
                'network_settings' => [
                    'path' => '/ws-path',
                    'headers' => ['Host' => 'example.com'],
                ],
            ],
        ]));

        $this->assertStringContainsString('type=ws', $uri);
        $this->assertStringContainsString('path=%2Fws-path', $uri);
        $this->assertStringContainsString('host=example.com', $uri);
        $this->assertStringNotContainsString('obfs=websocket', $uri);
    }

    public function test_vless_grpc_uri(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'network' => 'grpc',
                'network_settings' => ['serviceName' => 'test-service'],
            ],
        ]));

        $this->assertStringContainsString('type=grpc', $uri);
        $this->assertStringContainsString('serviceName=test-service', $uri);
        $this->assertStringNotContainsString('obfs=grpc', $uri);
    }

    public function test_vless_ipv6_host_is_wrapped(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'host' => '2001:db8::1',
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'network' => 'tcp',
            ],
        ]));

        $this->assertStringStartsWith('vless://' . self::UUID . '@[2001:db8::1]:443?', $uri);
    }

    public function test_vless_name_is_encoded_in_fragment(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'name' => '中文 节点',
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'network' => 'tcp',
            ],
        ]));

        $this->assertStringContainsString('#' . rawurlencode('中文 节点'), $uri);
        $this->assertStringNotContainsString('中文 节点', $uri);
    }

    public function test_vless_tcp_http_header_is_mapped_to_standard_fields(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'network' => 'tcp',
                'network_settings' => [
                    'header' => [
                        'type' => 'http',
                        'request' => [
                            'path' => ['/header-path'],
                            'headers' => ['Host' => ['example.com']],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('type=tcp', $uri);
        $this->assertStringContainsString('headerType=http', $uri);
        $this->assertStringContainsString('path=%2Fheader-path', $uri);
        $this->assertStringContainsString('host=example.com', $uri);

        $query = $this->parseQuery($uri);
        $this->assertSame('tcp', $query['type']);
        $this->assertSame('http', $query['headerType']);
        $this->assertSame('/header-path', $query['path']);
        $this->assertSame('example.com', $query['host']);

        $this->assertStringNotContainsString('obfs=', $uri);
        $this->assertStringNotContainsString('obfsParam=', $uri);
    }

    public function test_vless_tcp_plain_header_emits_no_header_fields(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'network' => 'tcp',
                'network_settings' => [
                    'header' => ['type' => 'none'],
                ],
            ],
        ]));

        $this->assertStringContainsString('type=tcp', $uri);
        $this->assertStringNotContainsString('headerType=', $uri);
        $this->assertStringNotContainsString('path=', $uri);
        $this->assertStringNotContainsString('obfs=', $uri);
    }

    public function test_vless_kcp_keeps_transport_and_uses_header_type_and_seed(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'network' => 'kcp',
                'network_settings' => [
                    'seed' => 'test-seed',
                    'header' => ['type' => 'srtp'],
                ],
            ],
        ]));

        $this->assertStringContainsString('type=kcp', $uri);
        $this->assertStringContainsString('headerType=srtp', $uri);
        $this->assertStringContainsString('seed=test-seed', $uri);

        $query = $this->parseQuery($uri);
        $this->assertSame('kcp', $query['type']);
        $this->assertSame('srtp', $query['headerType']);
        $this->assertSame('test-seed', $query['seed']);

        $this->assertStringNotContainsString('obfs=kcp', $uri);
    }

    public function test_vless_tfo_is_emitted(): void
    {
        $uri = Shadowrocket::buildVless(self::UUID, $this->makeServer([
            'protocol_settings' => [
                'tls' => 1,
                'tls_settings' => ['server_name' => 'example.com'],
                'network' => 'tcp',
            ],
        ]));

        $this->assertStringContainsString('tfo=1', $uri);
        $this->assertStringNotContainsString('remark=', $uri);
    }

    private function makeServer(array $overrides = []): array
    {
        return array_merge([
            'name' => 'test-node',
            'host' => 'example.com',
            'port' => 443,
            'protocol_settings' => [],
        ], $overrides);
    }

    private function parseQuery(string $uri): array
    {
        $query = parse_url(trim($uri), PHP_URL_QUERY);
        $result = [];
        foreach (explode('&', (string) $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $result[$key] = rawurldecode($value);
        }
        return $result;
    }
}
