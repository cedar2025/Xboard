<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Protocols\General;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Protocol prefix mapping for server names
     */
    private const PROTOCOL_PREFIXES = [
        'hysteria' => [
            1 => '[Hy]',
            2 => '[Hy2]'
        ],
        'vless' => '[vless]',
        'shadowsocks' => '[ss]',
        'vmess' => '[vmess]',
        'trojan' => '[trojan]',
    ];

    // 支持hy2 的客户端版本列表
    private const CLIENT_VERSIONS = [
        'NekoBox' => '1.2.7',
        'sing-box' => '1.5.0',
        'stash' => '2.5.0',
        'Shadowrocket' => '1993',
        'ClashMetaForAndroid' => '2.9.0',
        'Nekoray' => '3.24',
        'verge' => '1.3.8',
        'ClashX Meta' => '1.3.5',
        'Hiddify' => '0.1.0',
        'loon' => '637',
        'v2rayng' => '1.9.5',
        'v2rayN' => '6.31',
        'surge' => '2398'
    ];

    private const ALLOWED_TYPES = ['vmess', 'vless', 'trojan', 'hysteria', 'shadowsocks', 'hysteria2'];

    public function subscribe(Request $request)
    {
        $request->validate([
            'types' => ['nullable', 'string'],
            'filter' => ['nullable', 'string'],
            'flag' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $userService = new UserService();

        if (!$userService->isAvailable($user)) {
            return response()->json(['message' => 'Account unavailable'], 403);
        }

        $types = $this->getFilteredTypes($request->input('types', 'all'));
        $filterArr = $this->getFilterArray($request->input('filter'));
        $clientInfo = $this->getClientInfo($request);

        // Get available servers and apply filters
        $servers = ServerService::getAvailableServers($user);
        $serversFiltered = $this->filterServers(
            servers: $servers,
            types: $types,
            filters: $filterArr,
            supportHy2: $clientInfo['supportHy2']
        );

        $this->setSubscribeInfoToServers($serversFiltered, $user, count($servers) - count($serversFiltered));
        $serversFiltered = $this->addPrefixToServerName($serversFiltered);

        // Handle protocol response
        if ($clientInfo['flag']) {
            foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                $className = 'App\\Protocols\\' . basename($file, '.php');
                $protocol = new $className($user, $serversFiltered);
                if (
                    collect($protocol->getFlags())
                        ->contains(fn($f) => stripos($clientInfo['flag'], $f) !== false)
                ) {
                    return $protocol->handle();
                }
            }
        }

        return (new General($user, $serversFiltered))->handle();
    }

    private function getFilteredTypes(string $types): array
    {
        return $types === 'all'
            ? self::ALLOWED_TYPES
            : array_values(array_intersect(
                explode('|', str_replace(['|', '｜', ','], '|', $types)),
                self::ALLOWED_TYPES
            ));
    }

    private function getFilterArray(?string $filter): ?array
    {
        return mb_strlen($filter ?? '') > 20 ? null :
            explode('|', str_replace(['|', '｜', ','], '|', $filter));
    }

    private function getClientInfo(Request $request): array
    {
        $flag = strtolower($request->input('flag') ?? $request->header('User-Agent', ''));
        preg_match('/\/v?(\d+(\.\d+){0,2})/', $flag, $matches);
        $version = $matches[1] ?? null;

        $supportHy2 = $version ? $this->checkHy2Support($flag, $version) : true;

        return [
            'flag' => $flag,
            'version' => $version,
            'supportHy2' => $supportHy2
        ];
    }

    private function checkHy2Support(string $flag, string $version): bool
    {
        foreach (self::CLIENT_VERSIONS as $client => $minVersion) {
            if (stripos($flag, $client) !== false) {
                return version_compare($version, $minVersion, '>=');
            }
        }
        return true;
    }

    private function filterServers(array $servers, array $types, ?array $filters, bool $supportHy2): array
    {
        return collect($servers)->reject(function ($server) use ($types, $filters, $supportHy2) {
            // Check Hysteria2 compatibility
            if ($server['type'] === 'hysteria' && optional($server['protocol_settings'])['version'] === 2) {
                if (!in_array('hysteria2', $types) || !$supportHy2) {
                    return true;
                }
            }
            // Apply custom filters
            if ($filters) {
                return !collect($filters)->contains(function ($filter) use ($server) {
                    return stripos($server['name'], $filter) !== false
                        || in_array($filter, $server['tags'] ?? []);
                });
            }
            return false;
        })->values()->all();
    }

    /**
     * Summary of setSubscribeInfoToServers
     * @param mixed $servers
     * @param mixed $user
     * @param mixed $rejectServerCount
     * @return void
     */
    private function setSubscribeInfoToServers(&$servers, $user, $rejectServerCount = 0)
    {
        if (!isset($servers[0]))
            return;
        if ($rejectServerCount > 0) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "过滤掉{$rejectServerCount}条线路",
            ]));
        }
        if (!(int) admin_setting('show_info_to_server_enable', 0))
            return;
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : '长期有效';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        array_unshift($servers, array_merge($servers[0], [
            'name' => "套餐到期：{$expiredDate}",
        ]));
        if ($resetDay) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "距离下次重置剩余：{$resetDay} 天",
            ]));
        }
        array_unshift($servers, array_merge($servers[0], [
            'name' => "剩余流量：{$remainingTraffic}",
        ]));
    }

    /**
     * Add protocol prefix to server names if enabled in admin settings
     *
     * @param array<int, array<string, mixed>> $servers
     * @return array<int, array<string, mixed>>
     */
    private function addPrefixToServerName(array $servers): array
    {
        if (!admin_setting('show_protocol_to_server_enable', false)) {
            return $servers;
        }

        return collect($servers)
            ->map(function (array $server): array {
                $server['name'] = $this->getPrefixedServerName($server);
                return $server;
            })
            ->all();
    }
    /**
     * Get server name with protocol prefix
     *
     * @param array<string, mixed> $server
     */
    private function getPrefixedServerName(array $server): string
    {
        $type = $server['type'] ?? '';
        if (!isset(self::PROTOCOL_PREFIXES[$type])) {
            return $server['name'] ?? '';
        }

        $prefix = is_array(self::PROTOCOL_PREFIXES[$type])
            ? self::PROTOCOL_PREFIXES[$type][$server['protocol_settings']['version'] ?? 1] ?? ''
            : self::PROTOCOL_PREFIXES[$type];

        return $prefix . ($server['name'] ?? '');
    }
}
