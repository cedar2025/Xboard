<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\NodeSyncService;
use App\Services\NodeRegistry;
use App\Services\ServerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class NodeWebSocketServer extends Command
{
    protected $signature = 'ws-server
        {action=start : start | stop | restart | reload | status}
        {--d : Start in daemon mode}
        {--host=0.0.0.0 : Listen address}
        {--port=8076 : Listen port}';

    protected $description = 'Start the WebSocket server for node-panel synchronization';

    /** Auth timeout in seconds — close unauthenticated connections */
    private const AUTH_TIMEOUT = 10;

    /** Ping interval in seconds */
    private const PING_INTERVAL = 55;

    public function handle(): void
    {
        global $argv;
        $action = $this->argument('action');

        // 重新构建 argv 供 Workerman 解析
        $argv[1] = $action;
        if ($this->option('d')) {
            $argv[2] = '-d';
        }

        $host = $this->option('host');
        $port = $this->option('port');

        $worker = new Worker("websocket://{$host}:{$port}");
        $worker->count = 1;
        $worker->name = 'xboard-ws-server';

        // 设置日志和 PID 文件路径
        $logPath = storage_path('logs');
        if (!is_dir($logPath)) {
            mkdir($logPath, 0777, true);
        }
        Worker::$logFile = $logPath . '/xboard-ws-server.log'; // 指向具体文件，避免某些环境 php://stdout 的 stat 失败
        Worker::$pidFile = $logPath . '/xboard-ws-server.pid';

        $worker->onWorkerStart = function (Worker $worker) {
            $this->info("[WS] Worker started, pid={$worker->id}");
            $this->subscribeRedis();

            // Periodic ping to detect dead connections
            Timer::add(self::PING_INTERVAL, function () {
                foreach (NodeRegistry::getConnectedNodeIds() as $nodeId) {
                    $conn = NodeRegistry::get($nodeId);
                    if ($conn) {
                        $conn->send(json_encode(['event' => 'ping']));
                    }
                }
            });
        };

        $worker->onConnect = function (TcpConnection $conn) {
            // Set auth timeout — must authenticate within N seconds or get disconnected
            $conn->authTimer = Timer::add(self::AUTH_TIMEOUT, function () use ($conn) {
                if (empty($conn->nodeId)) {
                    $conn->close(json_encode([
                        'event' => 'error',
                        'data' => ['message' => 'auth timeout'],
                    ]));
                }
            }, [], false);
        };

        $worker->onWebSocketConnect = function (TcpConnection $conn, $httpMessage) {
            // Parse query string from the WebSocket upgrade request
            // In Workerman 4.x/5.x with onWebSocketConnect, the second arg can be a string or Request object
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

            $node = Server::find($nodeId);
            if (!$node) {
                $conn->close(json_encode([
                    'event' => 'error',
                    'data' => ['message' => 'node not found'],
                ]));
                return;
            }

            // Auth passed — cancel timeout, register connection
            if (isset($conn->authTimer)) {
                Timer::del($conn->authTimer);
            }

            $conn->nodeId = $nodeId;
            NodeRegistry::add($nodeId, $conn);
            Cache::put("node_ws_alive:{$nodeId}", true, 86400);

            Log::info("[WS] Node#{$nodeId} connected", [
                'remote' => $conn->getRemoteIp(),
                'total' => NodeRegistry::count(),
            ]);

            // Send auth success
            $conn->send(json_encode([
                'event' => 'auth.success',
                'data' => ['node_id' => $nodeId],
            ]));

            // Push full sync (config + users) immediately
            $this->pushFullSync($nodeId, $node);
        };

        $worker->onMessage = function (TcpConnection $conn, $data) {
            $msg = json_decode($data, true);
            if (!is_array($msg)) {
                return;
            }

            $event = $msg['event'] ?? '';

            switch ($event) {
                case 'pong':
                    // Heartbeat response — node is alive
                    if (!empty($conn->nodeId)) {
                        Cache::put("node_ws_alive:{$conn->nodeId}", true, 86400);
                    }
                    break;
                default:
                    // Future: handle other node-initiated messages if needed
                    break;
            }
        };

        $worker->onClose = function (TcpConnection $conn) {
            if (!empty($conn->nodeId)) {
                $nodeId = $conn->nodeId;
                NodeRegistry::remove($nodeId);
                Cache::forget("node_ws_alive:{$nodeId}");
                Log::info("[WS] Node#{$nodeId} disconnected", [
                    'total' => NodeRegistry::count(),
                ]);
            }
        };

        Worker::runAll();
    }

    /**
     * Subscribe to Redis pub/sub channel for receiving push commands from Laravel.
     * Laravel app publishes to "node:push" channel, Workerman picks it up and forwards to the right node.
     */
    private function subscribeRedis(): void
    {
        $host = config('database.redis.default.host', '127.0.0.1');
        $port = config('database.redis.default.port', 6379);

        // Handle Unix Socket connection
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

        // Get Laravel Redis prefix to match publish()
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

        $this->info("[WS] Subscribed to Redis channel: {$channel}");
    }

    /**
     * Push full config + users to a newly connected node.
     */
    private function pushFullSync(int $nodeId, Server $node): void
    {
        // Push config
        $config = ServerService::buildNodeConfig($node);
        NodeRegistry::send($nodeId, 'sync.config', ['config' => $config]);

        // Push users
        $users = ServerService::getAvailableUsers($node)->toArray();
        NodeRegistry::send($nodeId, 'sync.users', ['users' => $users]);

        Log::info("[WS] Full sync pushed to node#{$nodeId}", [
            'users' => count($users),
        ]);
    }
}
