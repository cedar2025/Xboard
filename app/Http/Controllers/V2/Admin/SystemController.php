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
            $statistics['info'] = LogModel::where('level', 'info')->count();
            $statistics['warning'] = LogModel::where('level', 'warning')->count();
            $statistics['error'] = LogModel::where('level', 'error')->count();
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
}
