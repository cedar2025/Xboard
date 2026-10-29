<?php

namespace Tests\Unit\Console;

use App\Models\Server;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CheckExpiredCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        admin_setting([
            'expiry_remove_enable' => 1,
            'expiry_grace_seconds' => 60,
        ]);
    }

    public function test_it_removes_expired_users_from_online_nodes_and_deduplicates(): void
    {
        $user = $this->createUser(time() - 3600, 2);
        $server = $this->createServer();

        Cache::put("node_ws_alive:{$server->id}", true);

        $this->artisan('check:expired')
            ->expectsOutputToContain('1 notified')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has($this->notifiedKey($user)));

        $this->artisan('check:expired')
            ->expectsOutputToContain('0 notified, 1 already notified')
            ->assertExitCode(0);
    }

    public function test_it_ignores_active_users_and_users_without_group(): void
    {
        $active = $this->createUser(time() + 3600, 2);
        $withoutGroup = $this->createUser(time() - 3600, null);

        $this->artisan('check:expired')->assertExitCode(0);

        $this->assertFalse(Cache::has($this->notifiedKey($active)));
        $this->assertFalse(Cache::has($this->notifiedKey($withoutGroup)));
    }

    private function createUser(int $expiredAt, ?int $groupId): User
    {
        return User::query()->create([
            'email' => 'user' . uniqid() . '@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'uuid' => Helper::guid(true),
            'token' => Helper::guid(true),
            'group_id' => $groupId,
            'expired_at' => $expiredAt,
            'transfer_enable' => 1073741824,
            'u' => 0,
            'd' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    private function createServer(): Server
    {
        return Server::query()->create([
            'type' => 'vmess',
            'name' => 'test-node',
            'rate' => 1,
            'host' => '127.0.0.1',
            'port' => '443',
            'server_port' => 443,
            'group_ids' => ['2'],
            'show' => true,
            'enabled' => true,
        ]);
    }

    private function notifiedKey(User $user): string
    {
        return 'expiry_removed:' . $user->id . ':' . $user->expired_at;
    }
}
