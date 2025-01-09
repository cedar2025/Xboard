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

    private const DEFAULT_PROTOCOL_SETTINGS = [
        self::TYPE_TROJAN => [
            'allow_insecure' => false,
            'server_name' => null,
            'network' => null,
            'network_settings' => null
        ],
        self::TYPE_VMESS => [
            'tls' => 0,
            'network' => null,
            'rules' => null,
            'network_settings' => null,
            'tls_settings' => null
        ],
        self::TYPE_VLESS => [
            'tls' => false,
            'tls_settings' => null,
            'flow' => null,
            'network' => null,
            'network_settings' => null,
            'reality_settings' => [
                'allow_insecure' => false,
                'server_port' => null,
                'server_name' => null,
                'public_key' => null,
                'private_key' => null,
                'short_id' => null
            ]
        ],
        self::TYPE_SHADOWSOCKS => [
            'cipher' => null,
            'obfs' => null,
            'obfs_settings' => null
        ],
        self::TYPE_HYSTERIA => [
            'version' => 2,
            'bandwidth' => [
                'up' => null,
                'down' => null
            ],
            'obfs' => [
                'open' => false,
                'type' => 'salamander',
                'password' => null
            ],
            'tls' => [
                'server_name' => null,
                'allow_insecure' => false
            ]
        ],
        self::TYPE_TUIC => [
            'congestion_control' => 'cubic',
            'alpn' => ['h3'],
            'udp_relay_mode' => 'native',
            'allow_insecure' => false,
            'tls_settings' => null
        ]
    ];

    public function getProtocolSettingsAttribute($value)
    {
        $settings = json_decode($value, true) ?? [];
        $defaultSettings = self::DEFAULT_PROTOCOL_SETTINGS[$this->type] ?? [];

        return array_replace_recursive($defaultSettings, $settings);
    }

    public function setProtocolSettingsAttribute($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $defaultSettings = self::DEFAULT_PROTOCOL_SETTINGS[$this->type] ?? [];
        $mergedSettings = array_replace_recursive($defaultSettings, $value ?? []);

        $this->attributes['protocol_settings'] = json_encode($mergedSettings);
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
