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

    /** @var array<int, TcpConnection> machineId → connection */
    private static array $machineConnections = [];

    public static function add(int $nodeId, TcpConnection $conn): void
    {
        if (isset(self::$connections[$nodeId]) && self::$connections[$nodeId] !== $conn) {
            self::$connections[$nodeId]->close();
        }
        self::$connections[$nodeId] = $conn;
    }

    public static function addMachine(int $machineId, TcpConnection $conn): void
    {
        if (isset(self::$machineConnections[$machineId]) && self::$machineConnections[$machineId] !== $conn) {
            self::$machineConnections[$machineId]->close();
        }
        self::$machineConnections[$machineId] = $conn;
    }

    /**
     * Remove a node mapping only if it still points to the given connection.
     * Passing null removes unconditionally (backward compat for single-node mode).
     */
    public static function remove(int $nodeId, ?TcpConnection $conn = null): void
    {
        if ($conn !== null && isset(self::$connections[$nodeId]) && self::$connections[$nodeId] !== $conn) {
            return; // already replaced by a newer connection
        }
        unset(self::$connections[$nodeId]);
    }

    public static function removeMachine(int $machineId, ?TcpConnection $conn = null): void
    {
        if ($conn !== null && isset(self::$machineConnections[$machineId]) && self::$machineConnections[$machineId] !== $conn) {
            return;
        }
        unset(self::$machineConnections[$machineId]);
    }

    public static function get(int $nodeId): ?TcpConnection
    {
        return self::$connections[$nodeId] ?? null;
    }

    public static function getMachine(int $machineId): ?TcpConnection
    {
        return self::$machineConnections[$machineId] ?? null;
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

        // Machine-mode connections multiplex multiple node IDs through the same
        // socket, so node-scoped events must carry node_id for the client mux.
        if (!empty($conn->machineNodeIds) && $event !== 'sync.nodes' && !array_key_exists('node_id', $data)) {
            $data['node_id'] = $nodeId;
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
     * Update in-memory registry when a machine's node set changes.
     * Called from the WS process when a sync.nodes event is dispatched.
     */
    public static function refreshMachineNodes(int $machineId, array $newNodeIds): void
    {
        $conn = self::getMachine($machineId);
        if (!$conn) {
            return;
        }

        $oldNodeIds = $conn->machineNodeIds ?? [];

        // Remove nodes no longer on this machine
        foreach (array_diff($oldNodeIds, $newNodeIds) as $removedId) {
            self::remove($removedId, $conn);
        }

        // Add newly assigned nodes (via add() to close any stale standalone connection)
        foreach ($newNodeIds as $nodeId) {
            self::add($nodeId, $conn);
        }

        $conn->machineNodeIds = $newNodeIds;
    }

    public static function sendMachine(int $machineId, string $event, array $data): bool
    {
        $conn = self::getMachine($machineId);
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

    /**
     * @return int[]
     */
    public static function getConnectedMachineIds(): array
    {
        return array_keys(self::$machineConnections);
    }

    public static function machineCount(): int
    {
        return count(self::$machineConnections);
    }
}

