<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use App\Utils\Helper;
use App\Models\User;

class Server extends Model
{
    public const TYPE_HYSTERIA = 'hysteria';
    public const TYPE_VLESS = 'vless';
    public const TYPE_TROJAN = 'trojan';
    public const TYPE_VMESS = 'vmess';
    public const TYPE_TUIC = 'tuic';
    public const TYPE_SHADOWSOCKS = 'shadowsocks';

    public const STATUS_OFFLINE = 0;
    public const STATUS_ONLINE_NO_PUSH = 1;
    public const STATUS_ONLINE = 2;

    public const CHECK_INTERVAL = 300; // 5 minutes in seconds

    private const CIPHER_CONFIGURATIONS = [
        '2022-blake3-aes-128-gcm' => [
            'serverKeySize' => 16,
            'userKeySize' => 16,
        ],
        '2022-blake3-aes-256-gcm' => [
            'serverKeySize' => 32,
            'userKeySize' => 32,
        ],
        '2022-blake3-chacha20-poly1305' => [
            'serverKeySize' => 32,
            'userKeySize' => 32,
        ]
    ];

    public const TYPE_ALIASES = [
        'v2ray' => self::TYPE_VMESS,
        'hysteria2' => self::TYPE_HYSTERIA,
    ];

    public const VALID_TYPES = [
        self::TYPE_HYSTERIA,
        self::TYPE_VLESS,
        self::TYPE_TROJAN,
        self::TYPE_VMESS,
        self::TYPE_TUIC,
        self::TYPE_SHADOWSOCKS,
    ];

    protected $table = 'v2_server';

    protected $guarded = ['id'];
    protected $casts = [
        'group_ids' => 'array',
        'route_ids' => 'array',
        'tags' => 'array',
        'protocol_settings' => 'array',
        'last_check_at' => 'integer',
        'last_push_at' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    private const PROTOCOL_CONFIGURATIONS = [
        self::TYPE_TROJAN => [
            'allow_insecure' => ['type' => 'boolean', 'default' => false],
            'server_name' => ['type' => 'string', 'default' => null],
            'network' => ['type' => 'string', 'default' => null],
            'network_settings' => ['type' => 'array', 'default' => null]
        ],
        self::TYPE_VMESS => [
            'tls' => ['type' => 'integer', 'default' => 0],
            'network' => ['type' => 'string', 'default' => null],
            'rules' => ['type' => 'array', 'default' => null],
            'network_settings' => ['type' => 'array', 'default' => null],
            'tls_settings' => ['type' => 'array', 'default' => null]
        ],
        self::TYPE_VLESS => [
            'tls' => ['type' => 'integer', 'default' => 0],
            'tls_settings' => ['type' => 'array', 'default' => null],
            'flow' => ['type' => 'string', 'default' => null],
            'network' => ['type' => 'string', 'default' => null],
            'network_settings' => ['type' => 'array', 'default' => null],
            'reality_settings' => [
                'type' => 'object',
                'fields' => [
                    'allow_insecure' => ['type' => 'boolean', 'default' => false],
                    'server_port' => ['type' => 'string', 'default' => null],
                    'server_name' => ['type' => 'string', 'default' => null],
                    'public_key' => ['type' => 'string', 'default' => null],
                    'private_key' => ['type' => 'string', 'default' => null],
                    'short_id' => ['type' => 'string', 'default' => null]
                ]
            ]
        ],
        self::TYPE_SHADOWSOCKS => [
            'cipher' => ['type' => 'string', 'default' => null],
            'obfs' => ['type' => 'string', 'default' => null],
            'obfs_settings' => ['type' => 'array', 'default' => null]
        ],
        self::TYPE_HYSTERIA => [
            'version' => ['type' => 'integer', 'default' => 2],
            'bandwidth' => [
                'type' => 'object',
                'fields' => [
                    'up' => ['type' => 'integer', 'default' => null],
                    'down' => ['type' => 'integer', 'default' => null]
                ]
            ],
            'obfs' => [
                'type' => 'object',
                'fields' => [
                    'open' => ['type' => 'boolean', 'default' => false],
                    'type' => ['type' => 'string', 'default' => 'salamander'],
                    'password' => ['type' => 'string', 'default' => null]
                ]
            ],
            'tls' => [
                'type' => 'object',
                'fields' => [
                    'server_name' => ['type' => 'string', 'default' => null],
                    'allow_insecure' => ['type' => 'boolean', 'default' => false]
                ]
            ]
        ],
        self::TYPE_TUIC => [
            'version' => ['type' => 'integer', 'default' => 5],
            'congestion_control' => ['type' => 'string', 'default' => 'cubic'],
            'alpn' => ['type' => 'array', 'default' => ['h3']],
            'udp_relay_mode' => ['type' => 'string', 'default' => 'native'],
            'tls' => [
                'type' => 'object',
                'fields' => [
                    'server_name' => ['type' => 'string', 'default' => null],
                    'allow_insecure' => ['type' => 'boolean', 'default' => false]
                ]
            ]
        ]
    ];

    private function castValueWithConfig($value, array $config)
    {
        if ($value === null && $config['type'] !== 'object') {
            return $config['default'] ?? null;
        }

        return match ($config['type']) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'string' => (string) $value,
            'array' => (array) $value,
            'object' => is_array($value) ?
            $this->castSettingsWithConfig($value, $config['fields']) :
            $config['default'] ?? null,
            default => $value
        };
    }

    private function castSettingsWithConfig(array $settings, array $configs): array
    {
        $result = [];
        foreach ($configs as $key => $config) {
            $value = $settings[$key] ?? null;
            $result[$key] = $this->castValueWithConfig($value, $config);
        }
        return $result;
    }

    private function getDefaultSettings(array $configs): array
    {
        $defaults = [];
        foreach ($configs as $key => $config) {
            if ($config['type'] === 'object') {
                $defaults[$key] = $this->getDefaultSettings($config['fields']);
            } else {
                $defaults[$key] = $config['default'];
            }
        }
        return $defaults;
    }

    public function getProtocolSettingsAttribute($value)
    {
        $settings = json_decode($value, true) ?? [];
        $configs = self::PROTOCOL_CONFIGURATIONS[$this->type] ?? [];
        return $this->castSettingsWithConfig($settings, $configs);
    }

    public function setProtocolSettingsAttribute($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $configs = self::PROTOCOL_CONFIGURATIONS[$this->type] ?? [];
        $castedSettings = $this->castSettingsWithConfig($value ?? [], $configs);

        $this->attributes['protocol_settings'] = json_encode($castedSettings);
    }

    public function loadParentCreatedAt(): void
    {
        if ($this->parent_id) {
            $this->created_at = $this->parent()->value('created_at');
        }
    }

    public function loadServerStatus(): void
    {
        $type = strtoupper($this->type);
        $serverId = $this->parent_id ?: $this->id;

        $this->last_check_at = Cache::get(CacheKey::get("SERVER_{$type}_LAST_CHECK_AT", $serverId));
        $this->last_push_at = Cache::get(CacheKey::get("SERVER_{$type}_LAST_PUSH_AT", $serverId));
        $this->online = Cache::get(CacheKey::get("SERVER_{$type}_ONLINE_USER", $serverId)) ?? 0;
        $this->is_online = (time() - 300 > $this->last_check_at) ? 0 : 1;
        $this->available_status = $this->getAvailableStatus();
        $this->cache_key = "{$this->type}-{$this->id}-{$this->updated_at}-{$this->is_online}";
    }

    public function handlePortAllocation(): void
    {
        if (strpos($this->port, '-') !== false) {
            $this->ports = $this->port;
            $this->port = Helper::randomPort($this->port);
        } else {
            $this->port = (int) $this->port;
        }
    }

    public function generateShadowsocksPassword(User $user): void
    {
        if ($this->type !== self::TYPE_SHADOWSOCKS) {
            return;
        }

        $this->password = $user->uuid;

        $cipher = data_get($this, 'protocol_settings.cipher');
        if (!$cipher || !isset(self::CIPHER_CONFIGURATIONS[$cipher])) {
            return;
        }

        $config = self::CIPHER_CONFIGURATIONS[$cipher];
        $serverKey = Helper::getServerKey($this->created_at, $config['serverKeySize']);
        $userKey = Helper::uuidToBase64($user->uuid, $config['userKeySize']);
        $this->password = "{$serverKey}:{$userKey}";
    }

    public static function normalizeType(string $type): string
    {
        return strtolower(self::TYPE_ALIASES[$type] ?? $type);
    }

    public static function isValidType(string $type): bool
    {
        return in_array(self::normalizeType($type), self::VALID_TYPES, true);
    }

    public function getAvailableStatus(): int
    {
        $now = time();
        if (!$this->last_check_at || ($now - self::CHECK_INTERVAL) >= $this->last_check_at) {
            return self::STATUS_OFFLINE;
        }
        if (!$this->last_push_at || ($now - self::CHECK_INTERVAL) >= $this->last_push_at) {
            return self::STATUS_ONLINE_NO_PUSH;
        }
        return self::STATUS_ONLINE;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(StatServer::class, 'server_id', 'id');
    }

    public function groups()
    {
        return ServerGroup::whereIn('id', $this->group_ids)->get();
    }

    public function routes()
    {
        return ServerRoute::whereIn('id', $this->route_ids)->get();
    }

}
