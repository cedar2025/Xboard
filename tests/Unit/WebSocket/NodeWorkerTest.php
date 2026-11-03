<?php

namespace Tests\Unit\WebSocket;

use App\Models\Server;
use App\Models\ServerMachine;
use App\Services\DeviceStateService;
use App\Services\NodeRegistry;
use App\WebSocket\NodeWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Support\FakeNodeConnection;
use Tests\TestCase;
use Workerman\Worker;

class NodeWorkerTest extends TestCase
{
    use RefreshDatabase;

    private NodeWorker $worker;

    /** The Workerman worker carrying the callbacks exactly as they are wired in production. */
    private Worker $callbacks;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');

        $registry = new \ReflectionClass(NodeRegistry::class);
        $registry->setStaticPropertyValue('connections', []);
        $registry->setStaticPropertyValue('machineConnections', []);

        $devices = Mockery::mock(DeviceStateService::class);
        $devices->shouldReceive('clearNodesDevices')->andReturn([])->byDefault();
        $devices->shouldReceive('clearAllNodeDevices')->andReturn([])->byDefault();
        $this->app->instance(DeviceStateService::class, $devices);

        $this->worker = new NodeWorker('127.0.0.1', 0);
        $reflection = new \ReflectionClass($this->worker);
        $reflection->getMethod('setupCallbacks')->invoke($this->worker);
        $this->callbacks = $reflection->getProperty('worker')->getValue($this->worker);
    }

    private function connect(FakeNodeConnection $conn, ServerMachine $machine): void
    {
        ($this->callbacks->onWebSocketConnect)($conn, "/ws?machine_id={$machine->id}&token=machine-token");
    }

    public function test_machine_handshake_is_acknowledged_before_any_full_sync(): void
    {
        [$machine, $nodes] = $this->makeMachineWithNodes(2);
        $conn = new FakeNodeConnection();

        $this->connect($conn, $machine);

        $this->assertSame(['auth.success'], $conn->sentEvents());
        $this->assertSame($nodes, $conn->sent[0]['data']['node_ids']);
    }

    public function test_queued_full_sync_is_pushed_for_every_node_of_the_machine(): void
    {
        [$machine, $nodes] = $this->makeMachineWithNodes(2);
        $conn = new FakeNodeConnection();
        $this->connect($conn, $machine);

        $this->drain();

        $this->assertSame(
            ['auth.success', 'sync.config', 'sync.users', 'sync.config', 'sync.users'],
            $conn->sentEvents()
        );
        $this->assertSame($nodes, array_values(array_unique(array_column(array_column(array_slice($conn->sent, 1), 'data'), 'node_id'))));
    }

    public function test_full_sync_is_skipped_for_a_connection_that_closed_while_queued(): void
    {
        [$machine] = $this->makeMachineWithNodes(2);
        $conn = new FakeNodeConnection();
        $this->connect($conn, $machine);

        ($this->callbacks->onClose)($conn);
        $this->drain();

        $this->assertSame(['auth.success'], $conn->sentEvents());
    }

    public function test_full_sync_goes_only_to_the_connection_that_replaced_an_older_one(): void
    {
        [$machine] = $this->makeMachineWithNodes(1);
        $stale = new FakeNodeConnection();
        $current = new FakeNodeConnection();
        $this->connect($stale, $machine);
        $this->connect($current, $machine);

        $this->drain();

        $this->assertSame(['auth.success'], $stale->sentEvents());
        $this->assertSame(['auth.success', 'sync.config', 'sync.users'], $current->sentEvents());
    }

    public function test_database_failure_during_handshake_rejects_the_connection_without_throwing(): void
    {
        [$machine] = $this->makeMachineWithNodes(1);
        $conn = new FakeNodeConnection();
        Schema::drop('v2_server_machine');

        $this->connect($conn, $machine);

        $this->assertTrue($conn->closed);
        $this->assertSame('error', $conn->closedWith['event']);
        $this->assertSame([], $conn->sentEvents());
    }

    public function test_database_failure_while_handling_a_message_does_not_throw(): void
    {
        [$machine, $nodes] = $this->makeMachineWithNodes(1);
        $conn = new FakeNodeConnection();
        $this->connect($conn, $machine);
        Schema::drop('v2_server');

        ($this->callbacks->onMessage)($conn, json_encode([
            'event' => 'node.status',
            'data' => ['node_id' => $nodes[0]],
        ]));

        $this->assertFalse($conn->closed);
    }

    public function test_full_sync_failure_closes_only_the_affected_connection(): void
    {
        [$machine] = $this->makeMachineWithNodes(1);
        $conn = new FakeNodeConnection();
        $this->connect($conn, $machine);
        Schema::drop('v2_server');

        $this->drain();

        $this->assertTrue($conn->closed);
    }

    private function drain(): void
    {
        // The queue yields back to the event loop after a time budget; keep
        // ticking the way the timer would until it is empty.
        for ($i = 0; $i < 50 && $this->worker->drainFullSyncQueue() > 0; $i++) {
        }
    }

    /**
     * @return array{0: ServerMachine, 1: int[]}
     */
    private function makeMachineWithNodes(int $count): array
    {
        $machine = ServerMachine::create([
            'name' => 'test-machine',
            'token' => 'machine-token',
            'is_active' => true,
        ]);

        $nodeIds = [];
        for ($i = 0; $i < $count; $i++) {
            $nodeIds[] = Server::create([
                'name' => "test-node-{$i}",
                'type' => Server::TYPE_VMESS,
                'host' => '127.0.0.1',
                'port' => 443,
                'server_port' => 443,
                'rate' => '1',
                'group_id' => [1],
                'enabled' => true,
                'machine_id' => $machine->id,
                'sort' => $i,
            ])->id;
        }

        return [$machine, $nodeIds];
    }
}
