<?php

namespace Tests\Unit\Protocols;

use App\Protocols\SingBox;
use PHPUnit\Framework\TestCase;

class SingBoxRuleSetHttpClientTest extends TestCase
{
    private function config(array $ruleSets, array $route = []): array
    {
        return ['outbounds' => [['type' => 'direct', 'tag' => 'direct']], 'route' => array_merge(['rule_set' => $ruleSets], $route)];
    }

    public function test_download_detour_becomes_shared_http_client(): void
    {
        $out = SingBox::upgradeRuleSetDownloadDetour($this->config([
            ['tag' => 'geosite-cn', 'type' => 'remote', 'format' => 'binary', 'url' => 'https://a/1.srs', 'download_detour' => 'proxy'],
            ['tag' => 'geoip-cn', 'type' => 'remote', 'format' => 'binary', 'url' => 'https://a/2.srs', 'download_detour' => 'proxy'],
        ]));

        $this->assertSame([['tag' => 'rule-set-download', 'detour' => 'proxy']], $out['http_clients']);
        $this->assertSame('rule-set-download', $out['route']['default_http_client']);
        foreach ($out['route']['rule_set'] as $ruleSet) {
            $this->assertArrayNotHasKey('download_detour', $ruleSet);
            $this->assertSame('rule-set-download', $ruleSet['http_client']);
        }
    }

    public function test_distinct_detours_get_distinct_clients_and_first_is_default(): void
    {
        $out = SingBox::upgradeRuleSetDownloadDetour($this->config([
            ['tag' => 'a', 'type' => 'remote', 'url' => 'https://a/a.srs', 'download_detour' => 'proxy'],
            ['tag' => 'b', 'type' => 'remote', 'url' => 'https://a/b.srs', 'download_detour' => 'direct'],
        ]));

        $this->assertSame([['tag' => 'rule-set-download', 'detour' => 'proxy'], ['tag' => 'rule-set-download-2', 'detour' => 'direct']], $out['http_clients']);
        $this->assertSame('rule-set-download', $out['route']['default_http_client']);
        $this->assertSame('rule-set-download-2', $out['route']['rule_set'][1]['http_client']);
    }

    public function test_keeps_existing_http_client_default_and_tags(): void
    {
        $config = $this->config(
            [
                ['tag' => 'a', 'type' => 'remote', 'url' => 'https://a/a.srs', 'download_detour' => 'proxy', 'http_client' => 'mine'],
                ['tag' => 'b', 'type' => 'remote', 'url' => 'https://a/b.srs', 'download_detour' => 'proxy'],
            ],
            ['default_http_client' => 'mine']
        );
        $config['http_clients'] = [['tag' => 'mine'], ['tag' => 'rule-set-download']];
        $out = SingBox::upgradeRuleSetDownloadDetour($config);

        $this->assertSame('mine', $out['route']['rule_set'][0]['http_client']);
        $this->assertArrayNotHasKey('download_detour', $out['route']['rule_set'][0]);
        $this->assertSame('rule-set-download-2', $out['route']['rule_set'][1]['http_client']);
        $this->assertSame('mine', $out['route']['default_http_client']);
        $this->assertCount(3, $out['http_clients']);
    }

    public function test_noop_without_remote_download_detour(): void
    {
        $config = $this->config([
            ['tag' => 'local', 'type' => 'local', 'path' => 'x.srs'],
            ['tag' => 'r', 'type' => 'remote', 'url' => 'https://a/r.srs'],
        ]);
        $this->assertSame($config, SingBox::upgradeRuleSetDownloadDetour($config));
        $this->assertSame(['outbounds' => []], SingBox::upgradeRuleSetDownloadDetour(['outbounds' => []]));
    }
}
