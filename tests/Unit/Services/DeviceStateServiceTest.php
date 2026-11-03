<?php

namespace Tests\Unit\Services;

use App\Services\DeviceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class DeviceStateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.redis.options.prefix', 'xboard_database_');
    }

    public function test_devices_of_several_nodes_are_collected_in_a_single_scan(): void
    {
        $this->fakeDevices([
            1 => ['10:1.1.1.1' => time(), '11:2.2.2.2' => time(), '12:3.3.3.3' => time()],
            2 => ['11:fe80::1' => time()],
        ]);

        $devices = (new DeviceStateService())->getNodesDevices([10, 11, 13]);

        $this->assertSame([
            10 => [1 => ['1.1.1.1']],
            11 => [1 => ['2.2.2.2'], 2 => ['fe80::1']],
            13 => [],
        ], $devices);
    }

    public function test_single_node_lookup_keeps_its_shape(): void
    {
        $this->fakeDevices([
            1 => ['10:1.1.1.1' => time(), '11:2.2.2.2' => time()],
        ]);

        $this->assertSame([1 => ['1.1.1.1']], (new DeviceStateService())->getNodeDevices(10));
    }

    public function test_clearing_several_nodes_removes_only_their_devices_and_notifies_affected_users_together(): void
    {
        $this->fakeDevices([
            1 => ['10:1.1.1.1' => time(), '11:2.2.2.2' => time(), '12:3.3.3.3' => time()],
            2 => ['11:fe80::1' => time()],
        ]);
        Redis::shouldReceive('hdel')->once()->with('user_devices:1', '10:1.1.1.1');
        Redis::shouldReceive('hdel')->once()->with('user_devices:1', '11:2.2.2.2');
        Redis::shouldReceive('hdel')->once()->with('user_devices:2', '11:fe80::1');

        $service = Mockery::mock(DeviceStateService::class)->makePartial();
        $service->shouldReceive('notifyUpdates')->once()->with([1, 2]);

        $this->assertSame([1, 2], $service->clearNodesDevices([10, 11]));
    }

    public function test_batched_notification_stores_each_users_remaining_device_count(): void
    {
        $now = time();
        foreach ([1, 2, 3] as $id) {
            DB::table('v2_user')->insert([
                'id' => $id,
                'email' => "user{$id}@example.com",
                'password' => 'secret',
                'uuid' => "uuid-{$id}",
                'token' => "token-{$id}",
                'online_count' => 9,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Redis::shouldReceive('hgetall')->with('user_devices:1')->andReturn(['10:1.1.1.1' => $now, '11:1.1.1.1' => $now]);
        Redis::shouldReceive('hgetall')->with('user_devices:2')->andReturn([]);

        (new DeviceStateService())->notifyUpdates([1, 2]);

        $this->assertSame(
            [1 => 1, 2 => 0, 3 => 9],
            DB::table('v2_user')->orderBy('id')->pluck('online_count', 'id')->map(fn($count) => (int) $count)->all()
        );
    }

    public function test_clearing_no_nodes_does_not_touch_redis(): void
    {
        Redis::shouldReceive('keys')->never();

        $this->assertSame([], (new DeviceStateService())->clearNodesDevices([]));
    }

    /**
     * @param array<int, array<string, int>> $byUser userId => {"nodeId:ip": timestamp}
     */
    private function fakeDevices(array $byUser): void
    {
        Redis::shouldReceive('keys')->once()->with('user_devices:*')
            ->andReturn(array_map(fn($userId) => "xboard_database_user_devices:{$userId}", array_keys($byUser)));

        // One pipelined round trip for every hash, never one request per user.
        Redis::shouldReceive('hgetall')->never();
        Redis::shouldReceive('pipeline')->once()->andReturnUsing(function (callable $commands) use ($byUser) {
            $pipe = new class ($byUser) {
                public array $replies = [];

                public function __construct(private array $byUser)
                {
                }

                public function hgetall(string $key): void
                {
                    $this->replies[] = $this->byUser[(int) substr($key, strlen('user_devices:'))];
                }
            };
            $commands($pipe);

            return $pipe->replies;
        });
    }
}
