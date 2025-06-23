<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Log as LogModel;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\WaitTimeCalculator;
use App\Helpers\ResponseEnum;

class SystemController extends Controller
{
    public function getSystemStatus()
    {
        $data = [
            'schedule' => $this->getScheduleStatus(),
            'horizon' => $this->getHorizonStatus(),
            'schedule_last_runtime' => Cache::get(CacheKey::get('SCHEDULE_LAST_CHECK_AT', null)),
            'logs' => $this->getLogStatistics()
        ];
        return $this->success($data);
    }

    /**
     * 获取日志统计信息
     * 
     * @return array 各级别日志的数量统计
     */
    protected function getLogStatistics(): array
    {
        // 初始化日志统计数组
        $statistics = [
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'total' => 0
        ];

        if (class_exists(LogModel::class) && LogModel::count() > 0) {
            $statistics['info'] = LogModel::where('level', 'INFO')->count();
            $statistics['warning'] = LogModel::where('level', 'WARNING')->count();
            $statistics['error'] = LogModel::where('level', 'ERROR')->count();
            $statistics['total'] = LogModel::count();

            return $statistics;
        }
        return $statistics;
    }

    public function getQueueWorkload(WorkloadRepository $workload)
    {
        return $this->success(collect($workload->get())->sortBy('name')->values()->toArray());
    }

    protected function getScheduleStatus(): bool
    {
        return (time() - 120) < Cache::get(CacheKey::get('SCHEDULE_LAST_CHECK_AT', null));
    }

    protected function getHorizonStatus(): bool
    {
        if (!$masters = app(MasterSupervisorRepository::class)->all()) {
            return false;
        }

        return collect($masters)->contains(function ($master) {
            return $master->status === 'paused';
        }) ? false : true;
    }

    public function getQueueStats()
    {
        $data = [
            'failedJobs' => app(JobRepository::class)->countRecentlyFailed(),
            'jobsPerMinute' => app(MetricsRepository::class)->jobsProcessedPerMinute(),
            'pausedMasters' => $this->totalPausedMasters(),
            'periods' => [
                'failedJobs' => config('horizon.trim.recent_failed', config('horizon.trim.failed')),
                'recentJobs' => config('horizon.trim.recent'),
            ],
            'processes' => $this->totalProcessCount(),
            'queueWithMaxRuntime' => app(MetricsRepository::class)->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => app(MetricsRepository::class)->queueWithMaximumThroughput(),
            'recentJobs' => app(JobRepository::class)->countRecent(),
            'status' => $this->getHorizonStatus(),
            'wait' => collect(app(WaitTimeCalculator::class)->calculate())->take(1),
        ];
        return $this->success($data);
    }

    /**
     * Get the total process count across all supervisors.
     *
     * @return int
     */
    protected function totalProcessCount()
    {
        $supervisors = app(SupervisorRepository::class)->all();

        return collect($supervisors)->reduce(function ($carry, $supervisor) {
            return $carry + collect($supervisor->processes)->sum();
        }, 0);
    }

    /**
     * Get the number of master supervisors that are currently paused.
     *
     * @return int
     */
    protected function totalPausedMasters()
    {
        if (!$masters = app(MasterSupervisorRepository::class)->all()) {
            return 0;
        }

        return collect($masters)->filter(function ($master) {
            return $master->status === 'paused';
        })->count();
    }

    public function getSystemLog(Request $request)
    {
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('page_size') >= 10 ? $request->input('page_size') : 10;
        $level = $request->input('level');
        $keyword = $request->input('keyword');

        $builder = LogModel::orderBy('created_at', 'DESC')
            ->when($level, function ($query) use ($level) {
                return $query->where('level', strtoupper($level));
            })
            ->when($keyword, function ($query) use ($keyword) {
                return $query->where(function ($q) use ($keyword) {
                    $q->where('data', 'like', '%' . $keyword . '%')
                        ->orWhere('context', 'like', '%' . $keyword . '%')
                        ->orWhere('title', 'like', '%' . $keyword . '%')
                        ->orWhere('uri', 'like', '%' . $keyword . '%');
                });
            });

        $total = $builder->count();
        $res = $builder->forPage($current, $pageSize)
            ->get();

        return response([
            'data' => $res,
            'total' => $total
        ]);
    }

    public function getHorizonFailedJobs(Request $request, JobRepository $jobRepository)
    {
        $current = max(1, (int) $request->input('current', 1));
        $pageSize = max(10, (int) $request->input('page_size', 20));
        $offset = ($current - 1) * $pageSize;

        $failedJobs = collect($jobRepository->getFailed())
            ->sortByDesc('failed_at')
            ->slice($offset, $pageSize)
            ->values();

        $total = $jobRepository->countFailed();

        return response()->json([
            'data' => $failedJobs,
            'total' => $total,
            'current' => $current,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 清除系统日志
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearSystemLog(Request $request)
    {
        $request->validate([
            'days' => 'integer|min:0|max:365',
            'level' => 'string|in:info,warning,error,all',
            'limit' => 'integer|min:100|max:10000'
        ], [
            'days.required' => '请指定要清除多少天前的日志',
            'days.integer' => '天数必须为整数',
            'days.min' => '天数不能少于1天',
            'days.max' => '天数不能超过365天',
            'level.in' => '日志级别只能是：info、warning、error、all',
            'limit.min' => '单次清除数量不能少于100条',
            'limit.max' => '单次清除数量不能超过10000条'
        ]);

        $days = $request->input('days', 30); // 默认清除30天前的日志
        $level = $request->input('level', 'all'); // 默认清除所有级别
        $limit = $request->input('limit', 1000); // 默认单次清除1000条

        try {
            $cutoffDate = now()->subDays($days);

            // 构建查询条件
            $query = LogModel::where('created_at', '<', $cutoffDate->timestamp);

            if ($level !== 'all') {
                $query->where('level', strtoupper($level));
            }

            // 获取要删除的记录数量
            $totalCount = $query->count();

            if ($totalCount === 0) {
                return $this->success([
                    'message' => '没有找到符合条件的日志记录',
                    'deleted_count' => 0,
                    'total_count' => $totalCount
                ]);
            }

            // 分批删除，避免单次删除过多数据
            $deletedCount = 0;
            $batchSize = min($limit, 1000); // 每批最多1000条

            while ($deletedCount < $limit && $deletedCount < $totalCount) {
                $remainingLimit = min($batchSize, $limit - $deletedCount);

                $batchQuery = LogModel::where('created_at', '<', $cutoffDate->timestamp);
                if ($level !== 'all') {
                    $batchQuery->where('level', strtoupper($level));
                }

                $idsToDelete = $batchQuery->limit($remainingLimit)->pluck('id');

                if ($idsToDelete->isEmpty()) {
                    break;
                }

                $batchDeleted = LogModel::whereIn('id', $idsToDelete)->delete();
                $deletedCount += $batchDeleted;

                // 避免长时间占用数据库连接
                if ($deletedCount < $limit && $deletedCount < $totalCount) {
                    usleep(100000); // 暂停0.1秒
                }
            }

            return $this->success([
                'message' => '日志清除完成',
                'deleted_count' => $deletedCount,
                'total_count' => $totalCount,
                'remaining_count' => max(0, $totalCount - $deletedCount)
            ]);

        } catch (\Exception $e) {
            return $this->fail(ResponseEnum::HTTP_ERROR, null, '清除日志失败：' . $e->getMessage());
        }
    }

    /**
     * 获取日志清除统计信息
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLogClearStats(Request $request)
    {
        $days = $request->input('days', 30);
        $level = $request->input('level', 'all');

        try {
            $cutoffDate = now()->subDays($days);

            $query = LogModel::where('created_at', '<', $cutoffDate->timestamp);
            if ($level !== 'all') {
                $query->where('level', strtoupper($level));
            }

            $stats = [
                'days' => $days,
                'level' => $level,
                'cutoff_date' => $cutoffDate->format(format: 'Y-m-d H:i:s'),
                'total_logs' => LogModel::count(),
                'logs_to_clear' => $query->count(),
                'oldest_log' => LogModel::orderBy('created_at', 'asc')->first(),
                'newest_log' => LogModel::orderBy('created_at', 'desc')->first(),
            ];

            return $this->success($stats);

        } catch (\Exception $e) {
            return $this->fail(ResponseEnum::HTTP_ERROR, null, '获取统计信息失败：' . $e->getMessage());
        }
    }
}
