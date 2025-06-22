<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TrafficResetLog;
use App\Services\TrafficResetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 流量重置管理控制器
 */
class TrafficResetController extends Controller
{
  private TrafficResetService $trafficResetService;

  public function __construct(TrafficResetService $trafficResetService)
  {
    $this->trafficResetService = $trafficResetService;
  }

  /**
   * 获取流量重置日志列表
   */
  public function logs(Request $request): JsonResponse
  {
    $request->validate([
      'user_id' => 'nullable|integer',
      'user_email' => 'nullable|string',
      'reset_type' => 'nullable|string|in:' . implode(',', array_keys(TrafficResetLog::getResetTypeNames())),
      'trigger_source' => 'nullable|string|in:' . implode(',', array_keys(TrafficResetLog::getSourceNames())),
      'start_date' => 'nullable|date',
      'end_date' => 'nullable|date|after_or_equal:start_date',
      'per_page' => 'nullable|integer|min:1|max:10000',
      'page' => 'nullable|integer|min:1',
    ]);

    $query = TrafficResetLog::with(['user:id,email'])
      ->orderBy('reset_time', 'desc');

    // 筛选条件
    if ($request->filled('user_id')) {
      $query->where('user_id', $request->user_id);
    }

    if ($request->filled('user_email')) {
      $query->whereHas('user', function ($query) use ($request) {
        $query->where('email', 'like', '%' . $request->user_email . '%');
      });
    }

    if ($request->filled('reset_type')) {
      $query->where('reset_type', $request->reset_type);
    }

    if ($request->filled('trigger_source')) {
      $query->where('trigger_source', $request->trigger_source);
    }

    if ($request->filled('start_date')) {
      $query->where('reset_time', '>=', $request->start_date);
    }

    if ($request->filled('end_date')) {
      $query->where('reset_time', '<=', $request->end_date . ' 23:59:59');
    }

    $perPage = $request->get('per_page', 20);
    $logs = $query->paginate($perPage);

    // 格式化数据
    $formattedLogs = $logs->getCollection()->map(function (TrafficResetLog $log) {
      return [
        'id' => $log->id,
        'user_id' => $log->user_id,
        'user_email' => $log->user->email ?? 'N/A',
        'reset_type' => $log->reset_type,
        'reset_type_name' => $log->getResetTypeName(),
        'reset_time' => $log->reset_time,
        'old_traffic' => [
          'upload' => $log->old_upload,
          'download' => $log->old_download,
          'total' => $log->old_total,
          'formatted' => $log->formatTraffic($log->old_total),
        ],
        'new_traffic' => [
          'upload' => $log->new_upload,
          'download' => $log->new_download,
          'total' => $log->new_total,
          'formatted' => $log->formatTraffic($log->new_total),
        ],
        'trigger_source' => $log->trigger_source,
        'trigger_source_name' => $log->getSourceName(),
        'metadata' => $log->metadata,
        'created_at' => $log->created_at,
      ];
    });

    return response()->json([
      'data' => $formattedLogs->toArray(),
      'pagination' => [
        'current_page' => $logs->currentPage(),
        'last_page' => $logs->lastPage(),
        'per_page' => $logs->perPage(),
        'total' => $logs->total(),
      ],
    ]);
  }

  /**
   * 获取流量重置统计信息
   */
  public function stats(Request $request): JsonResponse
  {
    $request->validate([
      'days' => 'nullable|integer|min:1|max:365',
    ]);

    $days = $request->get('days', 30);
    $startDate = now()->subDays($days)->startOfDay();

    $stats = [
      'total_resets' => TrafficResetLog::where('reset_time', '>=', $startDate)->count(),
      'auto_resets' => TrafficResetLog::where('reset_time', '>=', $startDate)
        ->where('trigger_source', TrafficResetLog::SOURCE_AUTO)
        ->count(),
      'manual_resets' => TrafficResetLog::where('reset_time', '>=', $startDate)
        ->where('trigger_source', TrafficResetLog::SOURCE_MANUAL)
        ->count(),
      'cron_resets' => TrafficResetLog::where('reset_time', '>=', $startDate)
        ->where('trigger_source', TrafficResetLog::SOURCE_CRON)
        ->count(),
    ];

    return response()->json([
      'data' => $stats
    ]);
  }

  /**
   * 手动重置用户流量
   */
  public function resetUser(Request $request): JsonResponse
  {
    $request->validate([
      'user_id' => 'required|integer|exists:v2_user,id',
      'reason' => 'nullable|string|max:255',
    ]);

    $user = User::find($request->user_id);

            if (!$this->trafficResetService->canReset($user)) {
            return response()->json([
                'message' => __('traffic_reset.user_cannot_reset')
            ], 400);
        }

    $metadata = [];
    if ($request->filled('reason')) {
      $metadata['reason'] = $request->reason;
      $metadata['admin_id'] = auth()->user()?->id;
    }

    $success = $this->trafficResetService->manualReset($user, $metadata);

            if (!$success) {
            return response()->json([
                'message' => __('traffic_reset.reset_failed')
            ], 500);
        }

        return response()->json([
            'message' => __('traffic_reset.reset_success'),
      'data' => [
        'user_id' => $user->id,
        'email' => $user->email,
        'reset_time' => now(),
        'next_reset_at' => $user->fresh()->next_reset_at,
      ]
    ]);
  }



  /**
   * 获取用户重置历史
   */
  public function userHistory(Request $request, int $userId): JsonResponse
  {
    $request->validate([
      'limit' => 'nullable|integer|min:1|max:50',
    ]);

    $user = User::findOrFail($userId);
    $limit = $request->get('limit', 10);

    $history = $this->trafficResetService->getUserResetHistory($user, $limit);

    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\TrafficResetLog> $history */
    $data = $history->map(function (TrafficResetLog $log) {
      return [
        'id' => $log->id,
        'reset_type' => $log->reset_type,
        'reset_type_name' => $log->getResetTypeName(),
        'reset_time' => $log->reset_time,
        'old_traffic' => [
          'upload' => $log->old_upload,
          'download' => $log->old_download,
          'total' => $log->old_total,
          'formatted' => $log->formatTraffic($log->old_total),
        ],
        'trigger_source' => $log->trigger_source,
        'trigger_source_name' => $log->getSourceName(),
        'metadata' => $log->metadata,
      ];
    });

    return response()->json([
      "data" => [
        'user' => [
          'id' => $user->id,
          'email' => $user->email,
          'reset_count' => $user->reset_count,
          'last_reset_at' => $user->last_reset_at,
          'next_reset_at' => $user->next_reset_at,
        ],
        'history' => $data,
      ]
    ]);
  }


}