<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TsunamiDeviceLeaseService
{
    public const TTL = 300;

    private const ADMIT_SCRIPT = <<<'LUA'
local expired = redis.call('ZRANGEBYSCORE', KEYS[1], '-inf', ARGV[1])
if #expired > 0 then
  redis.call('ZREM', KEYS[1], unpack(expired))
  redis.call('HDEL', KEYS[2], unpack(expired))
end

local function slot_count()
  local seen = {}
  local count = 0
  local slots = redis.call('HVALS', KEYS[2])
  for _, slot in ipairs(slots) do
    if seen[slot] == nil then
      seen[slot] = true
      count = count + 1
    end
  end
  return count, seen
end

local current = redis.call('HGET', KEYS[2], ARGV[4])
local count, seen = slot_count()
if current ~= false and current ~= ARGV[3] then
  return {0, count}
end
if current == false and seen[ARGV[3]] == nil and count >= tonumber(ARGV[2]) then
  return {0, count}
end

redis.call('ZADD', KEYS[1], ARGV[1] + ARGV[5], ARGV[4])
redis.call('HSET', KEYS[2], ARGV[4], ARGV[3])
redis.call('EXPIRE', KEYS[1], ARGV[6])
redis.call('EXPIRE', KEYS[2], ARGV[6])

if current == false and seen[ARGV[3]] == nil then
  count = count + 1
end
return {1, count}
LUA;

    private const RENEW_SCRIPT = <<<'LUA'
local expired = redis.call('ZRANGEBYSCORE', KEYS[1], '-inf', ARGV[1])
if #expired > 0 then
  redis.call('ZREM', KEYS[1], unpack(expired))
  redis.call('HDEL', KEYS[2], unpack(expired))
end

if redis.call('HEXISTS', KEYS[2], ARGV[2]) == 0 then
  return {0, 0}
end

redis.call('ZADD', KEYS[1], ARGV[1] + ARGV[3], ARGV[2])
redis.call('EXPIRE', KEYS[1], ARGV[4])
redis.call('EXPIRE', KEYS[2], ARGV[4])

local seen = {}
local count = 0
for _, slot in ipairs(redis.call('HVALS', KEYS[2])) do
  if seen[slot] == nil then
    seen[slot] = true
    count = count + 1
  end
end
return {1, count}
LUA;

    private const RELEASE_SCRIPT = <<<'LUA'
local expired = redis.call('ZRANGEBYSCORE', KEYS[1], '-inf', ARGV[1])
if #expired > 0 then
  redis.call('ZREM', KEYS[1], unpack(expired))
  redis.call('HDEL', KEYS[2], unpack(expired))
end

redis.call('ZREM', KEYS[1], ARGV[2])
redis.call('HDEL', KEYS[2], ARGV[2])

if redis.call('ZCARD', KEYS[1]) == 0 then
  redis.call('DEL', KEYS[1])
  redis.call('DEL', KEYS[2])
  return {1, 0}
end

redis.call('EXPIRE', KEYS[1], ARGV[3])
redis.call('EXPIRE', KEYS[2], ARGV[3])
local seen = {}
local count = 0
for _, slot in ipairs(redis.call('HVALS', KEYS[2])) do
  if seen[slot] == nil then
    seen[slot] = true
    count = count + 1
  end
end
return {1, count}
LUA;

    public function admit(int $userId, int $deviceLimit, string $mode, string $sessionId, string $ip): array
    {
        if ($deviceLimit <= 0) {
            return ['allowed' => true, 'count' => 0, 'limited' => false];
        }

        $now = time();
        $slot = $this->slot($mode, $sessionId, $ip);
        $result = $this->evaluate(self::ADMIT_SCRIPT, $this->keys($userId), [
            $now,
            $deviceLimit,
            $slot,
            $sessionId,
            self::TTL,
            self::TTL,
        ]);

        return [
            'allowed' => (int) ($result[0] ?? 0) === 1,
            'count' => (int) ($result[1] ?? 0),
            'limited' => true,
        ];
    }

    public function renew(int $userId, int $deviceLimit, string $sessionId): array
    {
        if ($deviceLimit <= 0) {
            return ['allowed' => true, 'count' => 0, 'limited' => false];
        }

        $result = $this->evaluate(self::RENEW_SCRIPT, $this->keys($userId), [
            time(),
            $sessionId,
            self::TTL,
            self::TTL,
        ]);

        return [
            'allowed' => (int) ($result[0] ?? 0) === 1,
            'count' => (int) ($result[1] ?? 0),
            'limited' => true,
        ];
    }

    public function release(int $userId, string $sessionId): array
    {
        $result = $this->evaluate(self::RELEASE_SCRIPT, $this->keys($userId), [
            time(),
            $sessionId,
            self::TTL,
        ]);

        return [
            'released' => (int) ($result[0] ?? 0) === 1,
            'count' => (int) ($result[1] ?? 0),
        ];
    }

    public static function normalizeIP(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $value, $matches)) {
            $value = $matches[1];
        }

        $packed = @inet_pton($value);
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16
            && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return inet_ntop(substr($packed, 12));
        }
        return inet_ntop($packed);
    }

    private function keys(int $userId): array
    {
        $tag = '{' . $userId . '}';
        return [
            "tsunami:device:{$tag}:sessions",
            "tsunami:device:{$tag}:slots",
        ];
    }

    private function slot(string $mode, string $sessionId, string $ip): string
    {
        return $mode === 'session' ? "session:{$sessionId}" : "ip:{$ip}";
    }

    private function evaluate(string $script, array $keys, array $arguments): array
    {
        $result = Redis::command('eval', [
            $script,
            count($keys),
            ...$keys,
            ...$arguments,
        ]);
        return is_array($result) ? $result : [];
    }
}
