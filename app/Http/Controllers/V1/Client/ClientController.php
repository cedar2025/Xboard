<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Protocols\General;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;

class ClientController extends Controller
{

    // 支持hy2 的客户端版本列表
    const SupportedHy2ClientVersions = [
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
    // allowed types
    const AllowedTypes = ['vmess', 'vless', 'trojan', 'hysteria', 'shadowsocks', 'hysteria2'];

    public function subscribe(Request $request)
    {
        // filter types
        $types = $request->input('types', 'all');
        $typesArr = $types === 'all' ? self::AllowedTypes : array_values(array_intersect(explode('|', str_replace(['|', '｜', ','], "|", $types)), self::AllowedTypes));
        // filter keyword
        $filterArr = mb_strlen($filter = $request->input('filter')) > 20 ? null : explode("|", str_replace(['|', '｜', ','], "|", $filter));
        $flag = strtolower($request->input('flag') ?? $request->header('User-Agent', ''));
        $ip = $request->input('ip', $request->ip());
        // get client version
        $version = preg_match('/\/v?(\d+(\.\d+){0,2})/', $flag, $matches) ? $matches[1] : null;
        $supportHy2 = $version ? collect(self::SupportedHy2ClientVersions)
                ->contains(fn($minVersion, $client) => stripos($flag, $client) !== false && $this->versionCompare($version, $minVersion)) : true;
        $user = $request->user;
        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            // get ip location
            $ip2region = new \Ip2Region();
            $region = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? ($ip2region->memorySearch($ip)['region'] ?? null) : null;
            // get available servers
            $servers = ServerService::getAvailableServers($user);
            // filter servers
            $serversFiltered = $this->serverFilter($servers, $typesArr, $filterArr, $region, $supportHy2);
            $this->setSubscribeInfoToServers($serversFiltered, $user, count($servers) - count($serversFiltered));
            $servers = $serversFiltered;
            $this->addPrefixToServerName($servers);
            if ($flag) {
                foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                    $file = 'App\\Protocols\\' . basename($file, '.php');
                    $class = new $file($user, $servers);
                    $classFlags = explode(',', $class->flag);
                    foreach ($classFlags as $classFlag) {
                        if (stripos($flag, $classFlag) !== false) {
                            return $class->handle();
                        }
                    }
                }
            }
            $class = new General($user, $servers);
            return $class->handle();
        }
    }
    /**
     * Summary of serverFilter
     * @param mixed $typesArr
     * @param mixed $filterArr
     * @param mixed $region
     * @param mixed $supportHy2
     * @return array
     */
    private function serverFilter($servers, $typesArr, $filterArr, $region, $supportHy2)
    {
        return collect($servers)->reject(function ($server) use ($typesArr, $filterArr, $region, $supportHy2) {
            if ($server['type'] == "hysteria" && $server['version'] == 2) {
                if(!in_array('hysteria2', $typesArr)){
                    return true;
                }elseif(false == $supportHy2){
                    return true;
                }
            }

            if ($filterArr) {
                foreach ($filterArr as $filter) {
                    if (stripos($server['name'], $filter) !== false || in_array($filter, $server['tags'] ?? [])) {
                        return false;
                    }
                }
                return true;
            }

            if (strpos($region, '中国') !== false) {
                $excludes = $server['excludes'] ?? [];
                if (empty($excludes)) {
                    return false;
                }
                foreach ($excludes as $v) {
                    $excludeList = explode("|", str_replace(["｜", ",", " ", "，"], "|", $v));
                    foreach ($excludeList as $needle) {
                        if (stripos($region, $needle) !== false) {
                            return true;
                        }
                    }
                }
            }
        })->values()->all();
    }
    /*
     * add prefix to server name
     */
    private function addPrefixToServerName(&$servers)
    {
        // 线路名称增加协议类型
        if (admin_setting('show_protocol_to_server_enable')) {
            $typePrefixes = [
                'hysteria' => [1 => '[Hy]', 2 => '[Hy2]'],
                'vless' => '[vless]',
                'shadowsocks' => '[ss]',
                'vmess' => '[vmess]',
                'trojan' => '[trojan]',
            ];
            $servers = collect($servers)->map(function ($server) use ($typePrefixes) {
                if (isset($typePrefixes[$server['type']])) {
                    $prefix = is_array($typePrefixes[$server['type']]) ? $typePrefixes[$server['type']][$server['version']] : $typePrefixes[$server['type']];
                    $server['name'] = $prefix . $server['name'];
                }
                return $server;
            })->toArray();
        }
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
     * 判断版本号
     */

    function versionCompare($version1, $version2)
    {
        if (!preg_match('/^\d+(\.\d+){0,2}/', $version1) || !preg_match('/^\d+(\.\d+){0,2}/', $version2)) {
            return false;
        }
        $v1Parts = explode('.', $version1);
        $v2Parts = explode('.', $version2);

        $maxParts = max(count($v1Parts), count($v2Parts));

        for ($i = 0; $i < $maxParts; $i++) {
            $part1 = isset($v1Parts[$i]) ? (int) $v1Parts[$i] : 0;
            $part2 = isset($v2Parts[$i]) ? (int) $v2Parts[$i] : 0;

            if ($part1 < $part2) {
                return false;
            } elseif ($part1 > $part2) {
                return true;
            }
        }

        // 版本号相等
        return true;
    }
}
