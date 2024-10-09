<?php
namespace App\Services;

use App\Models\CommissionLog;
use App\Models\Order;
use App\Models\Stat;
use App\Models\StatServer;
use App\Models\StatUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class StatisticalService
{
    protected $userStats;
    protected $startAt;
    protected $endAt;
    protected $serverStats;
    protected $statServerKey;
    protected $statUserKey;
    protected $redis;

    public function __construct()
    {
        ini_set('memory_limit', -1);
        $this->redis = Redis::connection();

    }

    public function setStartAt($timestamp)
    {
        $this->startAt = $timestamp;
        $this->statServerKey = "stat_server_{$this->startAt}";
        $this->statUserKey = "stat_user_{$this->startAt}";
    }

    public function setEndAt($timestamp)
    {
        $this->endAt = $timestamp;
    }

    /**
     * 生成统计报表
     */
    public function generateStatData(): array
    {
        $startAt = $this->startAt;
        $endAt = $this->endAt;
        if (!$startAt || !$endAt) {
            $startAt = strtotime(date('Y-m-d'));
            $endAt = strtotime('+1 day', $startAt);
        }
        $data = [];
        $data['order_count'] = Order::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt)
            ->count();
        $data['order_total'] = Order::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt)
            ->sum('total_amount');
        $data['paid_count'] = Order::where('paid_at', '>=', $startAt)
            ->where('paid_at', '<', $endAt)
            ->whereNotIn('status', [0, 2])
            ->count();
        $data['paid_total'] = Order::where('paid_at', '>=', $startAt)
            ->where('paid_at', '<', $endAt)
            ->whereNotIn('status', [0, 2])
            ->sum('total_amount');
        $commissionLogBuilder = CommissionLog::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt);
        $data['commission_count'] = $commissionLogBuilder->count();
        $data['commission_total'] = $commissionLogBuilder->sum('get_amount');
        $data['register_count'] = User::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt)
            ->count();
        $data['invite_count'] = User::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt)
            ->whereNotNull('invite_user_id')
            ->count();
        $data['transfer_used_total'] = StatServer::where('created_at', '>=', $startAt)
            ->where('created_at', '<', $endAt)
            ->select(DB::raw('SUM(u) + SUM(d) as total'))
            ->value('total') ?? 0;
        return $data;
    }

    /**
     * 往服务器报表缓存正追加流量使用数据
     */
    public function statServer($serverId, $serverType, $u, $d)
    {
        $u_menber = "{$serverType}_{$serverId}_u"; //储存上传流量的集合成员
        $d_menber = "{$serverType}_{$serverId}_d"; //储存下载流量的集合成员
        $this->redis->zincrby($this->statServerKey, $u, $u_menber);
        $this->redis->zincrby($this->statServerKey, $d, $d_menber);
    }

    /**
     * 追加用户使用流量
     */
    public function statUser($rate, $userId, $u, $d)
    {
        $u_menber = "{$rate}_{$userId}_u"; //储存上传流量的集合成员
        $d_menber = "{$rate}_{$userId}_d"; //储存下载流量的集合成员
        $this->redis->zincrby($this->statUserKey, $u, $u_menber);
        $this->redis->zincrby($this->statUserKey, $d, $d_menber);
    }

    /**
     * 获取指定用户的流量使用情况
     */
    public function getStatUserByUserID(int|string $userId): array
    {

        $stats = [];
        $statsUser = $this->redis->zrange($this->statUserKey, 0, -1, true);
        foreach ($statsUser as $member => $value) {
            list($rate, $uid, $type) = explode('_', $member);
            if (intval($uid) !== intval($userId))
                continue;
            $key = "{$rate}_{$uid}";
            $stats[$key] = $stats[$key] ?? [
                'record_at' => $this->startAt,
                'server_rate' => floatval($rate),
                'u' => 0,
                'd' => 0,
                'user_id' => intval($userId),
            ];
            $stats[$key][$type] += $value;
        }
        return array_values($stats);
    }

    /**
     * 获取缓存中的用户报表
     */
    public function getStatUser()
    {
        $stats = [];
        $statsUser = $this->redis->zrange($this->statUserKey, 0, -1, true);
        foreach ($statsUser as $member => $value) {
            list($rate, $uid, $type) = explode('_', $member);
            $key = "{$rate}_{$uid}";
            $stats[$key] = $stats[$key] ?? [
                'record_at' => $this->startAt,
                'server_rate' => $rate,
                'u' => 0,
                'd' => 0,
                'user_id' => intval($uid),
            ];
            $stats[$key][$type] += $value;
        }
        return array_values($stats);
    }


    /**
     * 获取缓存中的服务器爆表
     */
    public function getStatServer()
    {
        $stats = [];
        $statsServer = $this->redis->zrange($this->statServerKey, 0, -1, true);
        foreach ($statsServer as $member => $value) {
            list($serverType, $serverId, $type) = explode('_', $member);
            $key = "{$serverType}_{$serverId}";
            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'server_id' => intval($serverId),
                    'server_type' => $serverType,
                    'u' => 0,
                    'd' => 0,
                ];
            }
            $stats[$key][$type] += $value;
        }
        return array_values($stats);

    }

    /**
     * 清除用户报表缓存数据
     */
    public function clearStatUser()
    {
        $this->redis->del($this->statUserKey);
    }

    /**
     * 清除服务器报表缓存数据
     */
    public function clearStatServer()
    {
        $this->redis->del($this->statServerKey);
    }

    public function getStatRecord($type)
    {
        switch ($type) {
            case "paid_total": {
                return Stat::select([
                    '*',
                    DB::raw('paid_total / 100 as paid_total')
                ])
                    ->where('record_at', '>=', $this->startAt)
                    ->where('record_at', '<', $this->endAt)
                    ->orderBy('record_at', 'ASC')
                    ->get();
            }
            case "commission_total": {
                return Stat::select([
                    '*',
                    DB::raw('commission_total / 100 as commission_total')
                ])
                    ->where('record_at', '>=', $this->startAt)
                    ->where('record_at', '<', $this->endAt)
                    ->orderBy('record_at', 'ASC')
                    ->get();
            }
            case "register_count": {
                return Stat::where('record_at', '>=', $this->startAt)
                    ->where('record_at', '<', $this->endAt)
                    ->orderBy('record_at', 'ASC')
                    ->get();
            }
        }
    }

    public function getRanking($type, $limit = 20)
    {
        switch ($type) {
            case 'server_traffic_rank': {
                return $this->buildServerTrafficRank($limit);
            }
            case 'user_consumption_rank': {
                return $this->buildUserConsumptionRank($limit);
            }
            case 'invite_rank': {
                return $this->buildInviteRank($limit);
            }
        }
    }

    private function buildInviteRank($limit)
    {
        $stats = User::select([
            'invite_user_id',
            DB::raw('count(*) as count')
        ])
            ->where('created_at', '>=', $this->startAt)
            ->where('created_at', '<', $this->endAt)
            ->whereNotNull('invite_user_id')
            ->groupBy('invite_user_id')
            ->orderBy('count', 'DESC')
            ->limit($limit)
            ->get();

        $users = User::whereIn('id', $stats->pluck('invite_user_id')->toArray())->get()->keyBy('id');
        foreach ($stats as $k => $v) {
            if (!isset($users[$v['invite_user_id']]))
                continue;
            $stats[$k]['email'] = $users[$v['invite_user_id']]['email'];
        }
        return $stats;
    }

    private function buildUserConsumptionRank($limit)
    {
        $stats = StatUser::select([
            'user_id',
            DB::raw('sum(u) as u'),
            DB::raw('sum(d) as d'),
            DB::raw('sum(u) + sum(d) as total')
        ])
            ->where('record_at', '>=', $this->startAt)
            ->where('record_at', '<', $this->endAt)
            ->groupBy('user_id')
            ->orderBy('total', 'DESC')
            ->limit($limit)
            ->get();
        $users = User::whereIn('id', $stats->pluck('user_id')->toArray())->get()->keyBy('id');
        foreach ($stats as $k => $v) {
            if (!isset($users[$v['user_id']]))
                continue;
            $stats[$k]['email'] = $users[$v['user_id']]['email'];
        }
        return $stats;
    }

    private function buildServerTrafficRank($limit)
    {
        return StatServer::select([
            'server_id',
            'server_type',
            DB::raw('sum(u) as u'),
            DB::raw('sum(d) as d'),
            DB::raw('sum(u) + sum(d) as total')
        ])
            ->where('record_at', '>=', $this->startAt)
            ->where('record_at', '<', $this->endAt)
            ->groupBy('server_id', 'server_type')
            ->orderBy('total', 'DESC')
            ->limit($limit)
            ->get();
    }
}
