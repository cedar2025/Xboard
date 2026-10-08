<?php

namespace Tests\Support;

use Workerman\Connection\TcpConnection;

/**
 * In-memory stand-in for a Workerman connection: records what the worker
 * sends instead of touching a socket.
 */
class FakeNodeConnection extends TcpConnection
{
    /** @var array<int, array<string, mixed>> */
    public array $sent = [];

    public bool $closed = false;

    public mixed $closedWith = null;

    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function send(mixed $sendBuffer, bool $raw = false): bool|null
    {
        $this->sent[] = json_decode($sendBuffer, true);
        return true;
    }

    public function close(mixed $data = null, bool $raw = false): void
    {
        $this->closed = true;
        $this->closedWith = is_string($data) ? json_decode($data, true) : $data;
    }

    public function getStatus(bool $rawOutput = true): int|string
    {
        return $this->closed ? self::STATUS_CLOSED : self::STATUS_ESTABLISHED;
    }

    public function getRemoteIp(): string
    {
        return '127.0.0.1';
    }

    /** @return string[] */
    public function sentEvents(): array
    {
        return array_column($this->sent, 'event');
    }
}
