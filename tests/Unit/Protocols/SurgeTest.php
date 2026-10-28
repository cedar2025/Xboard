<?php

namespace Tests\Unit\Protocols;

use App\Protocols\Surge;
use Tests\TestCase;

class SurgeTest extends TestCase
{
    private function hysteria2Server(array $obfs): array
    {
        return [
            'name' => 'hy2',
            'host' => 'example.com',
            'port' => 443,
            'protocol_settings' => [
                'version' => 2,
                'obfs' => $obfs,
                'tls' => ['server_name' => 'example.com', 'allow_insecure' => false],
            ],
        ];
    }

    public function test_hysteria2_with_salamander_obfs_includes_password(): void
    {
        $line = Surge::buildHysteria('uuid', $this->hysteria2Server([
            'open' => true,
            'type' => 'salamander',
            'password' => 'obfs-secret',
        ]));

        $this->assertStringContainsString('salamander-password=obfs-secret', $line);
    }

    public function test_hysteria2_without_obfs_has_no_salamander_password(): void
    {
        $line = Surge::buildHysteria('uuid', $this->hysteria2Server([
            'open' => false,
            'type' => 'salamander',
            'password' => 'obfs-secret',
        ]));

        $this->assertStringNotContainsString('salamander-password', $line);
    }
}
