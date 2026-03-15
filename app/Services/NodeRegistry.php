<?php

namespace App\Services;

use Workerman\Connection\TcpConnection;

/**
 * In-memory registry for active WebSocket node connections.
 * Runs inside the Workerman process.
 */
class NodeRegistry
{
    /** @var array<int, TcpConnection> nodeId → connection */
    private static array $connections = [];

    public static function add(int $nodeId, TcpConnection $conn): void
    {
        // Close existing connection for this node (if reconnecting)
        if (isset(self::$connections[$nodeId])) {
            self::$connections[$nodeId]->close();
        }
        self::$connections[$nodeId] = $conn;
    }

    public static function remove(int $nodeId): void
    {
        unset(self::$connections[$nodeId]);
    }

    public static function get(int $nodeId): ?TcpConnection
    {
        return self::$connections[$nodeId] ?? null;
    }

    /**
     * Send a JSON message to a specific node.
     */
    public static function send(int $nodeId, string $event, array $data): bool
    {
        $conn = self::get($nodeId);
        if (!$conn) {
            return false;
        }

        $payload = json_encode([
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
        ]);

        $conn->send($payload);
        return true;
    }

    /**
     * Get the connection for a node by ID, checking if it's still alive.
     */
    public static function isOnline(int $nodeId): bool
    {
        $conn = self::get($nodeId);
        return $conn !== null && $conn->getStatus() === TcpConnection::STATUS_ESTABLISHED;
    }

    /**
     * Get all connected node IDs.
     * @return int[]
     */
    public static function getConnectedNodeIds(): array
    {
        return array_keys(self::$connections);
    }

    public static function count(): int
    {
        return count(self::$connections);
    }
}
