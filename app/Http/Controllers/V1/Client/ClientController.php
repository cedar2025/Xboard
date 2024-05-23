<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Protocols\General;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    public function subscribe(Request $request)
    {
        // 节点类型筛选
        $allowedTypes = ['vmess', 'vless', 'trojan', 'hysteria', 'hysteria2', 'shadowsocks'];
        $types = $request->input('types', "vmess|vless|trojan|hysteria|shadowsocks");
        if ($types === "all") $types = implode('|', $allowedTypes);
        $typesArr =  $types ?  collect(explode('|', str_replace(['|','｜',','], "|" , $types)))->reject(function($type) use ($allowedTypes){
            return !in_array($type, $allowedTypes);
        })->values()->all() : [];

        //  节点关键词筛选字段获取
        $filterArr = (mb_strlen($request->input('filter')) > 20) ? null : explode("|" ,str_replace(['|','｜',','], "|" , $request->input('filter')));

        $flag = $request->input('flag') ?? $request->header('User-Agent', '');
        $flag = strtolower($flag);
        $ip = $request->input('ip') ?? $request->ip();

        preg_match('/\/v?(\d+(\.\d+){0,2})/', $flag, $matches);
        $version = $matches[1]??null;
        $supportedClientVersions = [
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
            'v2rayN' => '6.31',
            'surge' => '2398'
        ];
        $supportHy2 = true;
        if ($version) {
            $supportHy2 = collect($supportedClientVersions)
                ->contains(function ($minVersion, $client) use ($flag, $version) {
                    return stripos($flag, $client) !== false && $this->versionCompare($version, $minVersion);
                });
        }
        $user = $request->user;
        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            // 获取IP地址信息
            $ip2region = new \Ip2Region();
            $geo = filter_var($ip,FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip2region->memorySearch($ip) : [];
            $region = $geo['region'] ?? null;

            // 获取服务器列表
            $servers = ServerService::getAvailableServers($user);
            
            // 判断不满足，不满足的直接过滤掉
            $serversFiltered = collect($servers)->reject(function ($server) use ($typesArr, $filterArr, $region, $supportHy2){
                // 过滤类型
                if($typesArr){
                    // 默认过滤掉hysteria2 线路
                    if($server['type'] == "hysteria" && $server['version'] == 2 && !in_array('hysteria2', $typesArr) 
                        && !$supportHy2 
                        ){ 
                        return true;
                    }
                    if(!in_array($server['type'], $typesArr) && !($server['type'] == "hysteria" && $server['version'] == 2 && in_array('hysteria2', $typesArr))) return true;
                }
                // 过滤关键词
                if($filterArr){
                    $rejectFlag = true;
                    foreach($filterArr as $filter){
                        if(stripos($server['name'],$filter) !== false 
                        || in_array($filter, $server['tags'] ?? [])
                        ) $rejectFlag = false;
                    }
                    if($rejectFlag) return true;
                }
                // 过滤地区
                if(strpos($region, '中国') !== false){
                    $excludes = $server['excludes'];
                    if(blank($excludes)) return false;
                    foreach($excludes as $v){
                        $excludeList = explode("|",str_replace(["｜",","," ","，"],"|",$v));
                        $rejectFlag = false;
                        foreach($excludeList as $needle){
                            if(stripos($region, $needle) !== false){
                                return true;
                            }
                        }
                    };
                }
            })->values()->all();
            $this->setSubscribeInfoToServers($serversFiltered, $user, count($servers) - count($serversFiltered));
            
            $servers = $serversFiltered;
            
            // 线路名称增加协议类型
            if (admin_setting('show_protocol_to_server_enable')){
                $typePrefixes = [
                    'hysteria' => [1 => '[Hy]', 2 => '[Hy2]'],
                    'vless' => '[vless]',
                    'shadowsocks' => '[ss]',
                    'vmess' => '[vmess]',
                    'trojan' => '[trojan]',
                ];
                $servers = collect($servers)->map(function($server)use ($typePrefixes){
                    if (isset($typePrefixes[$server['type']])) {
                        // 如果是 hysteria 类型，根据版本选择前缀
                        $prefix = is_array($typePrefixes[$server['type']]) ? $typePrefixes[$server['type']][$server['version']] : $typePrefixes[$server['type']];
                        // 设置服务器名称
                        $server['name'] = $prefix . $server['name'];
                    }
                    return $server;
                })->toArray();
            }
            if ($flag) {
                foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                    $file = 'App\\Protocols\\' . basename($file, '.php');
                    $class = new $file($user, $servers);
                    $classFlags = explode(',', $class->flag);
                    $isMatch = function() use ($classFlags, $flag){
                        foreach ($classFlags as $classFlag){
                            if(stripos($flag, $classFlag) !== false) return true;
                        }
                        return false;
                    };
                    // 判断是否匹配
                    if ($isMatch()) {
                        return $class->handle();
                    }
                }
            }
            $class = new General($user, $servers);
            return $class->handle();
        }
    }

    private function setSubscribeInfoToServers(&$servers, $user, $rejectServerCount = 0)
    {
        if (!isset($servers[0])) return;
        if($rejectServerCount > 0){
            array_unshift($servers, array_merge($servers[0], [
                'name' => "去除{$rejectServerCount}条不合适线路",
            ]));
        }
        if (!(int)admin_setting('show_info_to_server_enable', 0)) return;
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : '长期有效';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        // 筛选提示
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

    function versionCompare($version1, $version2) {
        if (!preg_match('/^\d+(\.\d+){0,2}/', $version1) || !preg_match('/^\d+(\.\d+){0,2}/', $version2)) {
            return false;
        }
        $v1Parts = explode('.', $version1);
        $v2Parts = explode('.', $version2);

        $maxParts = max(count($v1Parts), count($v2Parts));

        for ($i = 0; $i < $maxParts; $i++) {
            $part1 = isset($v1Parts[$i]) ? (int)$v1Parts[$i] : 0;
            $part2 = isset($v2Parts[$i]) ? (int)$v2Parts[$i] : 0;

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
