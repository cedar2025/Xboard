<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLog;
use App\Models\Order;
use App\Models\Server;
use App\Models\Stat;
use App\Models\StatServer;
use App\Models\StatUser;
use App\Models\Ticket;
use App\Models\User;
use App\Services\StatisticalService;
use Illuminate\Http\Request;

class StatController extends Controller
{
    private $service;
    public function __construct(StatisticalService $service)
    {
        $this->service = $service;
    }
    public function getOverride(Request $request)
    {
        // 获取在线节点数
        $onlineNodes = Server::all()->filter(function ($server) {
            return !!$server->is_online;
        })->count();
        // 获取在线设备数和在线用户数
        $onlineDevices = User::where('t', '>=', time() - 600)
            ->sum('online_count');
        $onlineUsers = User::where('t', '>=', time() - 600)
            ->count();

        // 获取今日流量统计
        $todayStart = strtotime('today');
        $todayTraffic = StatServer::where('record_at', '>=', $todayStart)
            ->where('record_at', '<', time())
            ->selectRaw('SUM(u) as upload, SUM(d) as download, SUM(u + d) as total')
            ->first();

        // 获取本月流量统计
        $monthStart = strtotime(date('Y-m-1'));
        $monthTraffic = StatServer::where('record_at', '>=', $monthStart)
            ->where('record_at', '<', time())
            ->selectRaw('SUM(u) as upload, SUM(d) as download, SUM(u + d) as total')
            ->first();

        // 获取总流量统计
        $totalTraffic = StatServer::selectRaw('SUM(u) as upload, SUM(d) as download, SUM(u + d) as total')
            ->first();

        return [
            'data' => [
                'month_income' => Order::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', time())
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'month_register_total' => User::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', time())
                    ->count(),
                'ticket_pending_total' => Ticket::where('status', 0)
                    ->count(),
                'commission_pending_total' => Order::where('commission_status', 0)
                    ->where('invite_user_id', '!=', NULL)
                    ->whereNotIn('status', [0, 2])
                    ->where('commission_balance', '>', 0)
                    ->count(),
                'day_income' => Order::where('created_at', '>=', strtotime(date('Y-m-d')))
                    ->where('created_at', '<', time())
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'last_month_income' => Order::where('created_at', '>=', strtotime('-1 month', strtotime(date('Y-m-1'))))
                    ->where('created_at', '<', strtotime(date('Y-m-1')))
                    ->whereNotIn('status', [0, 2])
                    ->sum('total_amount'),
                'commission_month_payout' => CommissionLog::where('created_at', '>=', strtotime(date('Y-m-1')))
                    ->where('created_at', '<', time())
                    ->sum('get_amount'),
                'commission_last_month_payout' => CommissionLog::where('created_at', '>=', strtotime('-1 month', strtotime(date('Y-m-1'))))
                    ->where('created_at', '<', strtotime(date('Y-m-1')))
                    ->sum('get_amount'),
                // 新增统计数据
                'online_nodes' => $onlineNodes,
                'online_devices' => $onlineDevices,
                'online_users' => $onlineUsers,
                'today_traffic' => [
                    'upload' => $todayTraffic->upload ?? 0,
                    'download' => $todayTraffic->download ?? 0,
                    'total' => $todayTraffic->total ?? 0
                ],
                'month_traffic' => [
                    'upload' => $monthTraffic->upload ?? 0,
                    'download' => $monthTraffic->download ?? 0,
                    'total' => $monthTraffic->total ?? 0
                ],
                'total_traffic' => [
                    'upload' => $totalTraffic->upload ?? 0,
                    'download' => $totalTraffic->download ?? 0,
                    'total' => $totalTraffic->total ?? 0
                ]
            ]
        ];
    }

    /**
     * Get order statistics with filtering and pagination
     *
     * @param Request $request
     * @return array
     */
    public function getOrder(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'type' => 'nullable|in:paid_total,paid_count,commission_total,commission_count',
        ]);

        $query = Stat::where('record_type', 'd');

        // Apply date filters
        if ($request->input('start_date')) {
            $query->where('record_at', '>=', strtotime($request->input('start_date')));
        }
        if ($request->input('end_date')) {
            $query->where('record_at', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }

        $statistics = $query->orderBy('record_at', 'DESC')
            ->get();

        $summary = [
            'paid_total' => 0,
            'paid_count' => 0,
            'commission_total' => 0,
            'commission_count' => 0,
            'start_date' => $request->input('start_date', date('Y-m-d', $statistics->last()?->record_at)),
            'end_date' => $request->input('end_date', date('Y-m-d', $statistics->first()?->record_at)),
            'avg_paid_amount' => 0,
            'avg_commission_amount' => 0
        ];

        $dailyStats = [];
        foreach ($statistics as $statistic) {
            $date = date('Y-m-d', $statistic['record_at']);

            // Update summary
            $summary['paid_total'] += $statistic['paid_total'];
            $summary['paid_count'] += $statistic['paid_count'];
            $summary['commission_total'] += $statistic['commission_total'];
            $summary['commission_count'] += $statistic['commission_count'];

            // Calculate daily stats
            $dailyData = [
                'date' => $date,
                'paid_total' => $statistic['paid_total'],
                'paid_count' => $statistic['paid_count'],
                'commission_total' => $statistic['commission_total'],
                'commission_count' => $statistic['commission_count'],
                'avg_order_amount' => $statistic['paid_count'] > 0 ? round($statistic['paid_total'] / $statistic['paid_count'], 2) : 0,
                'avg_commission_amount' => $statistic['commission_count'] > 0 ? round($statistic['commission_total'] / $statistic['commission_count'], 2) : 0
            ];

            if ($request->input('type')) {
                $dailyStats[] = [
                    'date' => $date,
                    'value' => $statistic[$request->input('type')],
                    'type' => $this->getTypeLabel($request->input('type'))
                ];
            } else {
                $dailyStats[] = $dailyData;
            }
        }

        // Calculate averages for summary
        if ($summary['paid_count'] > 0) {
            $summary['avg_paid_amount'] = round($summary['paid_total'] / $summary['paid_count'], 2);
        }
        if ($summary['commission_count'] > 0) {
            $summary['avg_commission_amount'] = round($summary['commission_total'] / $summary['commission_count'], 2);
        }

        // Add percentage calculations to summary
        $summary['commission_rate'] = $summary['paid_total'] > 0
            ? round(($summary['commission_total'] / $summary['paid_total']) * 100, 2)
            : 0;

        return [
            'code' => 0,
            'message' => 'success',
            'data' => [
                'list' => array_reverse($dailyStats),
                'summary' => $summary,
            ]
        ];
    }

    /**
     * Get human readable label for statistic type
     *
     * @param string $type
     * @return string
     */
    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            'paid_total' => '收款金额',
            'paid_count' => '收款笔数',
            'commission_total' => '佣金金额(已发放)',
            'commission_count' => '佣金笔数(已发放)',
            default => $type
        };
    }

    // 获取当日实时流量排行
    public function getServerLastRank()
    {
        $data = $this->service->getServerRank();
        return $this->success(data: $data);
    }
    // 获取昨日节点流量排行
    public function getServerYesterdayRank()
    {
        $data = $this->service->getServerRank('yesterday');
        return $this->success($data);
    }

    public function getStatUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        $pageSize = $request->input('pageSize', 10);
        $records = StatUser::orderBy('record_at', 'DESC')
            ->where('user_id', $request->input('user_id'))
            ->paginate($pageSize);

        $data = $records->items();
        return [
            'data' => $data,
            'total' => $records->total(),
        ];
    }

    public function getStatRecord(Request $request)
    {
        return [
            'data' => $this->service->getStatRecord($request->input('type'))
        ];
    }

    /**
     * Get comprehensive statistics data including income, users, and growth rates
     */
    public function getStats()
    {
        $currentMonthStart = strtotime(date('Y-m-01'));
        $lastMonthStart = strtotime('-1 month', $currentMonthStart);
        $twoMonthsAgoStart = strtotime('-2 month', $currentMonthStart);

        // Today's start timestamp
        $todayStart = strtotime('today');
        $yesterdayStart = strtotime('-1 day', $todayStart);

        // 获取在线节点数
        $onlineNodes = Server::all()->filter(function ($server) {
            return !!$server->is_online;
        })->count();

        // 获取在线设备数和在线用户数
        $onlineDevices = User::where('t', '>=', time() - 600)
            ->sum('online_count');
        $onlineUsers = User::where('t', '>=', time() - 600)
            ->count();

        // 获取今日流量统计
        $todayTraffic = StatServer::where('record_at', '>=', $todayStart)
            ->where('record_at', '<', time())
            ->selectRaw('SUM(u) as upload, SUM(d) as download, SUM(u + d) as total')
            ->first();

        // 获取本月流量统计
        $monthTraffic = StatServer::where('record_at', '>=', $currentMonthStart)
            ->where('record_at', '<', time())
            ->selectRaw('SUM(u) as upload, SUM(d) as download, SUM(u + d) as total')
            ->first();

        // 获取总流量统计
        $totalTraffic = StatServer::selectRaw('SUM(u) as upload, SUM(d) as download, SUM(u + d) as total')
            ->first();

        // Today's income
        $todayIncome = Order::where('created_at', '>=', $todayStart)
            ->where('created_at', '<', time())
            ->whereNotIn('status', [0, 2])
            ->sum('total_amount');

        // Yesterday's income for day growth calculation
        $yesterdayIncome = Order::where('created_at', '>=', $yesterdayStart)
            ->where('created_at', '<', $todayStart)
            ->whereNotIn('status', [0, 2])
            ->sum('total_amount');

        // Current month income
        $currentMonthIncome = Order::where('created_at', '>=', $currentMonthStart)
            ->where('created_at', '<', time())
            ->whereNotIn('status', [0, 2])
            ->sum('total_amount');

        // Last month income
        $lastMonthIncome = Order::where('created_at', '>=', $lastMonthStart)
            ->where('created_at', '<', $currentMonthStart)
            ->whereNotIn('status', [0, 2])
            ->sum('total_amount');

        // Last month commission payout
        $lastMonthCommissionPayout = CommissionLog::where('created_at', '>=', $lastMonthStart)
            ->where('created_at', '<', $currentMonthStart)
            ->sum('get_amount');

        // Current month commission payout
        $currentMonthCommissionPayout = CommissionLog::where('created_at', '>=', $currentMonthStart)
            ->where('created_at', '<', time())
            ->sum('get_amount');

        // Current month new users
        $currentMonthNewUsers = User::where('created_at', '>=', $currentMonthStart)
            ->where('created_at', '<', time())
            ->count();

        // Total users
        $totalUsers = User::count();

        // Active users (users with valid subscription)
        $activeUsers = User::where(function ($query) {
            $query->where('expired_at', '>=', time())
                ->orWhere('expired_at', NULL);
        })->count();

        // Previous month income for growth calculation
        $twoMonthsAgoIncome = Order::where('created_at', '>=', $twoMonthsAgoStart)
            ->where('created_at', '<', $lastMonthStart)
            ->whereNotIn('status', [0, 2])
            ->sum('total_amount');

        // Previous month commission for growth calculation
        $twoMonthsAgoCommission = CommissionLog::where('created_at', '>=', $twoMonthsAgoStart)
            ->where('created_at', '<', $lastMonthStart)
            ->sum('get_amount');

        // Previous month users for growth calculation
        $lastMonthNewUsers = User::where('created_at', '>=', $lastMonthStart)
            ->where('created_at', '<', $currentMonthStart)
            ->count();

        // Calculate growth rates
        $monthIncomeGrowth = $lastMonthIncome > 0 ? round(($currentMonthIncome - $lastMonthIncome) / $lastMonthIncome * 100, 1) : 0;
        $lastMonthIncomeGrowth = $twoMonthsAgoIncome > 0 ? round(($lastMonthIncome - $twoMonthsAgoIncome) / $twoMonthsAgoIncome * 100, 1) : 0;
        $commissionGrowth = $twoMonthsAgoCommission > 0 ? round(($lastMonthCommissionPayout - $twoMonthsAgoCommission) / $twoMonthsAgoCommission * 100, 1) : 0;
        $userGrowth = $lastMonthNewUsers > 0 ? round(($currentMonthNewUsers - $lastMonthNewUsers) / $lastMonthNewUsers * 100, 1) : 0;
        $dayIncomeGrowth = $yesterdayIncome > 0 ? round(($todayIncome - $yesterdayIncome) / $yesterdayIncome * 100, 1) : 0;

        // 获取待处理工单和佣金数据
        $ticketPendingTotal = Ticket::where('status', 0)->count();
        $commissionPendingTotal = Order::where('commission_status', 0)
            ->where('invite_user_id', '!=', NULL)
            ->whereIn('status', [Order::STATUS_COMPLETED])
            ->where('commission_balance', '>', 0)
            ->count();

        return [
            'data' => [
                // 收入相关
                'todayIncome' => $todayIncome,
                'dayIncomeGrowth' => $dayIncomeGrowth,
                'currentMonthIncome' => $currentMonthIncome,
                'lastMonthIncome' => $lastMonthIncome,
                'monthIncomeGrowth' => $monthIncomeGrowth,
                'lastMonthIncomeGrowth' => $lastMonthIncomeGrowth,

                // 佣金相关
                'currentMonthCommissionPayout' => $currentMonthCommissionPayout,
                'lastMonthCommissionPayout' => $lastMonthCommissionPayout,
                'commissionGrowth' => $commissionGrowth,
                'commissionPendingTotal' => $commissionPendingTotal,

                // 用户相关
                'currentMonthNewUsers' => $currentMonthNewUsers,
                'totalUsers' => $totalUsers,
                'activeUsers' => $activeUsers,
                'userGrowth' => $userGrowth,
                'onlineUsers' => $onlineUsers,
                'onlineDevices' => $onlineDevices,

                // 工单相关
                'ticketPendingTotal' => $ticketPendingTotal,

                // 节点相关
                'onlineNodes' => $onlineNodes,

                // 流量统计
                'todayTraffic' => [
                    'upload' => $todayTraffic->upload ?? 0,
                    'download' => $todayTraffic->download ?? 0,
                    'total' => $todayTraffic->total ?? 0
                ],
                'monthTraffic' => [
                    'upload' => $monthTraffic->upload ?? 0,
                    'download' => $monthTraffic->download ?? 0,
                    'total' => $monthTraffic->total ?? 0
                ],
                'totalTraffic' => [
                    'upload' => $totalTraffic->upload ?? 0,
                    'download' => $totalTraffic->download ?? 0,
                    'total' => $totalTraffic->total ?? 0
                ]
            ]
        ];
    }

    /**
     * Get traffic ranking data for nodes or users
     * 
     * @param Request $request
     * @return array
     */
    public function getTrafficRank(Request $request)
    {
        $request->validate([
            'type' => 'required|in:node,user',
            'start_time' => 'nullable|integer|min:1000000000|max:9999999999',
            'end_time' => 'nullable|integer|min:1000000000|max:9999999999'
        ]);

        $type = $request->input('type');
        $startDate = $request->input('start_time', strtotime('-7 days'));
        $endDate = $request->input('end_time', time());
        $previousStartDate = $startDate - ($endDate - $startDate);
        $previousEndDate = $startDate;

        if ($type === 'node') {
            // Get node traffic data
            $currentData = StatServer::selectRaw('server_id as id, SUM(u + d) as value')
                ->where('record_at', '>=', $startDate)
                ->where('record_at', '<=', $endDate)
                ->groupBy('server_id')
                ->orderBy('value', 'DESC')
                ->limit(10)
                ->get();

            // Get previous period data for comparison
            $previousData = StatServer::selectRaw('server_id as id, SUM(u + d) as value')
                ->where('record_at', '>=', $previousStartDate)
                ->where('record_at', '<', $previousEndDate)
                ->whereIn('server_id', $currentData->pluck('id'))
                ->groupBy('server_id')
                ->get()
                ->keyBy('id');

        } else {
            // Get user traffic data
            $currentData = StatUser::selectRaw('user_id as id, SUM(u + d) as value')
                ->where('record_at', '>=', $startDate)
                ->where('record_at', '<=', $endDate)
                ->groupBy('user_id')
                ->orderBy('value', 'DESC')
                ->limit(10)
                ->get();

            // Get previous period data for comparison
            $previousData = StatUser::selectRaw('user_id as id, SUM(u + d) as value')
                ->where('record_at', '>=', $previousStartDate)
                ->where('record_at', '<', $previousEndDate)
                ->whereIn('user_id', $currentData->pluck('id'))
                ->groupBy('user_id')
                ->get()
                ->keyBy('id');
        }

        $result = [];
        foreach ($currentData as $data) {
            $previousValue = isset($previousData[$data->id]) ? $previousData[$data->id]->value : 0;
            $change = $previousValue > 0 ? round(($data->value - $previousValue) / $previousValue * 100, 1) : 0;

            $name = $type === 'node'
                ? optional(Server::find($data->id))->name ?? "Node {$data->id}"
                : optional(User::find($data->id))->email ?? "User {$data->id}";

            $result[] = [
                'id' => (string) $data->id,
                'name' => $name,
                'value' => $data->value, // Convert to GB
                'previousValue' => $previousValue, // Convert to GB
                'change' => $change,
                'timestamp' => date('c', $endDate)
            ];
        }

        return [
            'timestamp' => date('c'),
            'data' => $result
        ];
    }
}
