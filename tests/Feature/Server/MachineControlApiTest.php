<?php

namespace Tests\Feature\Server;

use App\Models\ServerMachine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

// Panel-side control channel: /server/machine/reload + /restart must publish
// control.reload / control.restart to the WS Redis channel.
class MachineControlApiTest extends TestCase
{
    use RefreshDatabase;

    private string $adminPath;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forever('admin_settings', [
            'server_token' => 'server-token',
            'server_ws_enable' => 0,
            'server_push_interval' => 60,
        ]);
        $this->adminPath = '/api/v2/' . hash('crc32b', config('app.key'));
    }

    private function admin(): User
    {
        return User::create([
            'email' => 'admin@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'token' => \Illuminate\Support\Str::random(32),
            'is_admin' => 1,
        ]);
    }

    public function test_reload_publishes_control_event_to_redis(): void
    {
        ServerMachine::create(['name' => 'm1', 'token' => 't1', 'is_active' => 1, 'last_seen_at' => now()->timestamp]);
        $published = [];
        Redis::shouldReceive('publish')->once()->andReturnUsing(function ($channel, $json) use (&$published) {
            $this->assertSame('node:push', $channel);
            $published = json_decode($json, true);
        });

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->adminPath . '/server/machine/reload', ['machine_id' => 1, 'node_id' => 6])
            ->assertOk()
            ->assertJsonPath('data', true);

        $this->assertSame(1, $published['machine_id']);
        $this->assertSame('control.reload', $published['event']);
        $this->assertSame(6, $published['data']['node_id']);
    }

    public function test_restart_without_node_id_targets_whole_machine(): void
    {
        ServerMachine::create(['name' => 'm1', 'token' => 't1', 'is_active' => 1, 'last_seen_at' => now()->timestamp]);
        $published = [];
        Redis::shouldReceive('publish')->once()->andReturnUsing(function ($channel, $json) use (&$published) {
            $published = json_decode($json, true);
        });

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->adminPath . '/server/machine/restart', ['machine_id' => 1])
            ->assertOk();

        $this->assertSame('control.restart', $published['event']);
        $this->assertArrayNotHasKey('node_id', $published['data']);
    }

    public function test_control_rejected_for_inactive_machine(): void
    {
        ServerMachine::create(['name' => 'm1', 'token' => 't1', 'is_active' => 0, 'last_seen_at' => now()->timestamp]);
        Redis::shouldReceive('publish')->never();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson($this->adminPath . '/server/machine/reload', ['machine_id' => 1])
            ->assertStatus(400);
    }
}
