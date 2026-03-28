<?php

namespace App\WebSocket;

use App\Models\Server;
use App\Services\DeviceStateService;
use App\Services\NodeRegistry;
use App\Services\ServerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class NodeWorker
{
    private const AUTH_TIMEOUT = 10;
    private const PING_INTERVAL = 55;

    private Worker $worker;

    private array $handlers = [
        'pong' => [NodeEventHandlers::class, 'handlePong'],
        'node.status' => [NodeEventHandlers::class, 'handleNodeStatus'],
        'report.devices' => [NodeEventHandlers::class, 'handleDeviceReport'],
        'request.devices' => [NodeEventHandlers::class, 'handleDeviceRequest'],
    ];

    public function __construct(string $host, int $port)
    {
        $this->worker = new Worker("websocket://{$host}:{$port}");
        $this->worker->count = 1;
        $this->worker->name = 'xboard-ws-server';
    }

    public function run(): void
    {
        $this->setupLogging();
        $this->setupCallbacks();
        Worker::runAll();
    }

    private function setupLogging(): void
    {
        $logPath = storage_path('logs');
        if (!is_dir($logPath)) {
            mkdir($logPath, 0777, true);
        }
        Worker::$logFile = $logPath . '/xboard-ws-server.log';
        Worker::$pidFile = $logPath . '/xboard-ws-server.pid';
    }

    private function setupCallbacks(): void
    {
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        $this->worker->onConnect = [$this, 'onConnect'];
        $this->worker->onWebSocketConnect = [$this, 'onWebSocketConnect'];
        $this->worker->onMessage = [$this, 'onMessage'];
        $this->worker->onClose = [$this, 'onClose'];
    }

    public function onWorkerStart(Worker $worker): void
    {
        Log::info("[WS] Worker started, pid={$worker->id}");
        $this->subscribeRedis();
        $this->setupTimers();
    }

    private function setupTimers(): void
    {
        // Ping timer
        Timer::add(self::PING_INTERVAL, function () {
            foreach (NodeRegistry::getConnectedNodeIds() as $nodeId) {
                $conn = NodeRegistry::get($nodeId);
                if ($conn) {
                    $conn->send(json_encode(['event' => 'ping']));
                }
            }
        });

        // Device state push timer
        Timer::add(10, function () {
            $pendingNodeIds = Redis::spop('device:push_pending_nodes', 100);
            if (empty($pendingNodeIds)) {
                return;
            }

            $service = app(DeviceStateService::class);
            foreach ($pendingNodeIds as $nodeId) {
                $nodeId = (int) $nodeId;
                if (NodeRegistry::get($nodeId) !== null) {
                    NodeEventHandlers::pushDeviceStateToNode($nodeId, $service);
                }
            }
        });
    }

    public function onConnect(TcpConnection $conn): void
    {
        $conn->authTimer = Timer::add(self::AUTH_TIMEOUT, function () use ($conn) {
            if (empty($conn->nodeId)) {
                $conn->close(json_encode([
                    'event' => 'error',
                    'data' => ['message' => 'auth timeout'],
                ]));
            }
        }, [], false);
    }

    public function onWebSocketConnect(TcpConnection $conn, $httpMessage): void
    {
        $queryString = '';
        if (is_string($httpMessage)) {
            $queryString = parse_url($httpMessage, PHP_URL_QUERY) ?? '';
        } elseif ($httpMessage instanceof \Workerman\Protocols\Http\Request) {
            $queryString = $httpMessage->queryString();
        }

        parse_str($queryString, $params);
        $token = $params['token'] ?? '';
        $nodeId = (int) ($params['node_id'] ?? 0);

        // Authenticate
        $serverToken = admin_setting('server_token', '');
        if ($token === '' || $serverToken === '' || !hash_equals($serverToken, $token)) {
            $conn->close(json_encode([
                'event' => 'error',
                'data' => ['message' => 'invalid token'],
            ]));
            return;
        }

        $node = ServerService::getServer($nodeId, null);
        if (!$node) {
            $conn->close(json_encode([
                'event' => 'error',
                'data' => ['message' => 'node not found'],
            ]));
            return;
        }

        // Auth passed
        if (isset($conn->authTimer)) {
            Timer::del($conn->authTimer);
        }

        $conn->nodeId = $nodeId;
        NodeRegistry::add($nodeId, $conn);
        Cache::put("node_ws_alive:{$nodeId}", true, 86400);

        // Clear old device data
        app(DeviceStateService::class)->clearAllNodeDevices($nodeId);

        Log::debug("[WS] Node#{$nodeId} connected", [
            'remote' => $conn->getRemoteIp(),
            'total' => NodeRegistry::count(),
        ]);

        // Send auth success
        $conn->send(json_encode([
            'event' => 'auth.success',
            'data' => ['node_id' => $nodeId],
        ]));

        // Push full sync
        NodeEventHandlers::pushFullSync($conn, $node);
    }

    public function onMessage(TcpConnection $conn, $data): void
    {
        $msg = json_decode($data, true);
        if (!is_array($msg)) {
            return;
        }

        $event = $msg['event'] ?? '';
        $nodeId = $conn->nodeId ?? null;

        if (isset($this->handlers[$event]) && $nodeId) {
            $handler = $this->handlers[$event];
            $handler($conn, $nodeId, $msg['data'] ?? []);
        }
    }

    public function onClose(TcpConnection $conn): void
    {
        if (!empty($conn->nodeId)) {
            $nodeId = $conn->nodeId;
            NodeRegistry::remove($nodeId);
            Cache::forget("node_ws_alive:{$nodeId}");

            $service = app(DeviceStateService::class);
            $affectedUserIds = $service->clearAllNodeDevices($nodeId);
            foreach ($affectedUserIds as $userId) {
                $service->notifyUpdate($userId);
            }

            Log::debug("[WS] Node#{$nodeId} disconnected", [
                'total' => NodeRegistry::count(),
                'affected_users' => count($affectedUserIds),
            ]);
        }
    }

    private function subscribeRedis(): void
    {
        $host = config('database.redis.default.host', '127.0.0.1');
        $port = config('database.redis.default.port', 6379);

        if (str_starts_with($host, '/')) {
            $redisUri = "unix://{$host}";
        } else {
            $redisUri = "redis://{$host}:{$port}";
        }

        $redis = new \Workerman\Redis\Client($redisUri);

        $password = config('database.redis.default.password');
        if ($password) {
            $redis->auth($password);
        }

        $prefix = config('database.redis.options.prefix', '');
        $channel = $prefix . 'node:push';

        $redis->subscribe([$channel], function ($chan, $message) {
            $payload = json_decode($message, true);
            if (!is_array($payload)) {
                return;
            }

            $nodeId = $payload['node_id'] ?? null;
            $event = $payload['event'] ?? '';
            $data = $payload['data'] ?? [];

            if (!$nodeId || !$event) {
                return;
            }

            $sent = NodeRegistry::send((int) $nodeId, $event, $data);
            if ($sent) {
                Log::debug("[WS] Pushed {$event} to node#{$nodeId}");
            }
        });

        Log::info("[WS] Subscribed to Redis channel: {$channel}");
    }
}
