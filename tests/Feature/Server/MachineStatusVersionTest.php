<?php

namespace Tests\Feature\Server;

use App\Console\Commands\CheckMachineOffline;
use App\Models\ServerMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MachineStatusVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forever('admin_settings', [
            'server_token' => 'server-token',
            'server_ws_enable' => 0,
            'server_push_interval' => 60,
        ]);
    }

    private function machine(array $attrs = []): ServerMachine
    {
        return ServerMachine::create(array_merge([
            'name' => 'test-machine',
            'token' => 'machine-token',
            'is_active' => 1,
            'last_seen_at' => now()->timestamp,
        ], $attrs));
    }

    public function test_machine_status_persists_agent_version(): void
    {
        $machine = $this->machine();

        $response = $this->postJson('/api/v2/server/machine/status', [
            'machine_id' => $machine->id,
            'token' => 'machine-token',
            'cpu' => 12.5,
            'mem' => ['total' => 1000, 'used' => 500],
            'agent_version' => 'v1.2.3-abc1234',
        ]);

        $response->assertOk();
        $this->assertSame('v1.2.3-abc1234', $machine->refresh()->agent_version);
    }

    public function test_machine_status_without_version_keeps_old_value(): void
    {
        $machine = $this->machine(['agent_version' => 'v0.9.0']);

        $this->postJson('/api/v2/server/machine/status', [
            'machine_id' => $machine->id,
            'token' => 'machine-token',
            'cpu' => 10,
            'mem' => ['total' => 1000, 'used' => 500],
        ]);

        $this->assertSame('v0.9.0', $machine->refresh()->agent_version);
    }

    public function test_admin_fetch_reports_online_state_and_version(): void
    {
        $online = $this->machine(['name' => 'online', 'agent_version' => 'v1.2.3']);
        $offline = $this->machine([
            'name' => 'offline',
            'token' => 'tok2',
            'last_seen_at' => now()->subSeconds(600)->timestamp,
        ]);

        $admin = \App\Models\User::create([
            'email' => 'admin@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'token' => \Illuminate\Support\Str::random(32),
            'is_admin' => 1,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v2/' . hash('crc32b', config('app.key')) . '/server/machine/fetch');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('name');
        $this->assertTrue($data['online']['is_online']);
        $this->assertFalse($data['offline']['is_online']);
        $this->assertSame('v1.2.3', $data['online']['agent_version']);
    }

    public function test_offline_alert_flips_once_per_state_change(): void
    {
        config()->set('cache.setting_store', 'array');
        $machine = $this->machine(['last_seen_at' => now()->subSeconds(600)->timestamp]);

        // 第一次：上线→离线，告警一次
        $this->artisan(CheckMachineOffline::class)->expectsOutputToContain('机器离线')->assertSuccessful();
        // 第二次：仍离线，不再告警
        $this->artisan(CheckMachineOffline::class)->assertSuccessful();
        // 心跳恢复 → 恢复通知
        $machine->update(['last_seen_at' => now()->timestamp]);
        $this->artisan(CheckMachineOffline::class)->expectsOutputToContain('机器恢复')->assertSuccessful();
    }

    public function test_high_load_alerts_after_sustained_rounds_and_recovers(): void
    {
        config()->set('cache.setting_store', 'array');
        Cache::forget('machine_highload_rounds_1');
        Cache::forget('machine_highload_alerted_1');

        $machine = $this->machine([
            'load_status' => ['cpu' => 95.0, 'mem' => ['total' => 1000, 'used' => 500]],
        ]);

        // 前 4 轮高负载：累计但不告警
        for ($i = 0; $i < 4; $i++) {
            $this->artisan(CheckMachineOffline::class)->assertSuccessful();
        }

        // 第 5 轮：触发告警
        $this->artisan(CheckMachineOffline::class)->expectsOutputToContain('机器高负载')->assertSuccessful();

        // 负载恢复：发恢复通知，且状态复位（再来高负载需重新累计）
        $machine->update(['load_status' => ['cpu' => 10.0, 'mem' => ['total' => 1000, 'used' => 500]]]);
        $this->artisan(CheckMachineOffline::class)->expectsOutputToContain('负载恢复')->assertSuccessful();
    }
}
