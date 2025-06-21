<?php

namespace App\Console\Commands;

use App\Services\TrafficResetService;
use App\Utils\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetTraffic extends Command
{
    /**
   * The name and signature of the console command.
     */
  protected $signature = 'reset:traffic {--batch-size=100 : 分批处理的批次大小} {--dry-run : 预演模式，不实际执行重置} {--max-time=300 : 最大执行时间（秒）}';

    /**
   * The console command description.
     */
  protected $description = '流量重置 - 分批处理所有需要重置的用户';

    /**
   * 流量重置服务
     */
  private TrafficResetService $trafficResetService;

  /**
   * Create a new command instance.
   */
  public function __construct(TrafficResetService $trafficResetService)
    {
        parent::__construct();
    $this->trafficResetService = $trafficResetService;
  }

  /**
   * Execute the console command.
   */
  public function handle(): int
  {
    $batchSize = (int) $this->option('batch-size');
    $dryRun = $this->option('dry-run');
    $maxTime = (int) $this->option('max-time');

    $this->info('🚀 开始执行流量重置任务...');
    $this->info("批次大小: {$batchSize} 用户/批");
    $this->info("最大执行时间: {$maxTime} 秒");

    if ($dryRun) {
      $this->warn('⚠️  预演模式 - 不会实际执行重置操作');
    }

    // 设置最大执行时间
    set_time_limit($maxTime);

    $startTime = microtime(true);

    try {
      if ($dryRun) {
        $result = $this->performDryRun($batchSize);
      } else {
        // 使用游标分页和进度回调
        $result = $this->trafficResetService->batchCheckReset($batchSize, function ($progress) {
          $this->info("📦 处理第 {$progress['batch_number']} 批 ({$progress['batch_size']} 用户) - 已处理: {$progress['total_processed']}");
        });
      }

      $this->displayResults($result, $dryRun);

      return self::SUCCESS;

    } catch (\Exception $e) {
      $this->error("❌ 任务执行失败: {$e->getMessage()}");

      Log::error('流量重置命令执行失败', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'options' => [
          'batch_size' => $batchSize,
          'dry_run' => $dryRun,
          'max_time' => $maxTime,
        ],
      ]);

      return self::FAILURE;
    }
  }

  /**
   * 显示执行结果
   */
  private function displayResults(array $result, bool $dryRun): void
  {
    $this->info("✅ 任务完成！");
    $this->line('');

    if ($dryRun) {
      $this->info("📊 预演结果统计:");
      $this->info("📋 待处理用户数: {$result['total_found']}");
      $this->info("⏱️  预计处理时间: ~{$result['estimated_duration']} 秒");
      $this->info("🗂️  预计批次数: {$result['estimated_batches']}");
    } else {
      $this->info("📊 执行结果统计:");
      $this->info("👥 处理用户总数: {$result['total_processed']}");
      $this->info("🔄 重置用户数量: {$result['total_reset']}");
      $this->info("📦 处理批次数量: {$result['total_batches']}");
      $this->info("⏱️  总执行时间: {$result['duration']} 秒");

      if ($result['error_count'] > 0) {
        $this->warn("⚠️  错误数量: {$result['error_count']}");
        $this->warn("详细错误信息请查看日志");
      } else {
        $this->info("✨ 无错误发生");
      }

      // 显示性能指标
      if ($result['total_processed'] > 0) {
        $avgTime = round($result['duration'] / $result['total_processed'], 4);
        $this->info("⚡ 平均处理速度: {$avgTime} 秒/用户");
      }
    }
  }

  /**
   * 执行预演模式
   */
  private function performDryRun(int $batchSize): array
  {
    $this->info("🔍 扫描需要重置的用户...");

    $totalUsers = \App\Models\User::where('next_reset_at', '<=', time())
      ->whereNotNull('next_reset_at')
      ->where(function ($query) {
        $query->where('expired_at', '>', time())
          ->orWhereNull('expired_at');
      })
      ->where('banned', 0)
      ->whereNotNull('plan_id')
      ->count();

    if ($totalUsers === 0) {
      $this->info("😴 当前没有需要重置的用户");
      return [
        'total_found' => 0,
        'estimated_duration' => 0,
        'estimated_batches' => 0,
      ];
    }

    $this->info("找到 {$totalUsers} 个需要重置的用户");

    // 预计批次数
    $estimatedBatches = ceil($totalUsers / $batchSize);

    // 预计执行时间（基于经验值：每个用户平均0.1秒）
    $estimatedDuration = round($totalUsers * 0.1, 1);

    $this->info("将分 {$estimatedBatches} 个批次处理（每批 {$batchSize} 用户）");

    // 显示前几个用户的详情作为示例
    if ($this->option('verbose') || $totalUsers <= 20) {
      $sampleUsers = \App\Models\User::where('next_reset_at', '<=', time())
        ->whereNotNull('next_reset_at')
        ->where(function ($query) {
          $query->where('expired_at', '>', time())
            ->orWhereNull('expired_at');
        })
        ->where('banned', 0)
        ->whereNotNull('plan_id')
        ->with('plan')
        ->limit(min(20, $totalUsers))
        ->get();

      $table = [];
      foreach ($sampleUsers as $user) {
        $table[] = [
          'ID' => $user->id,
          '邮箱' => substr($user->email, 0, 20) . (strlen($user->email) > 20 ? '...' : ''),
          '套餐' => $user->plan->name ?? 'N/A',
          '下次重置' => $user->next_reset_at->format('m-d H:i'),
          '当前流量' => Helper::trafficConvert(($user->u ?? 0) + ($user->d ?? 0)),
          '重置次数' => $user->reset_count,
        ];
      }

      if (!empty($table)) {
        $this->info("📋 示例用户列表" . ($totalUsers > 20 ? "（显示前20个）：" : "："));
        $this->table([
          'ID',
          '邮箱',
          '套餐',
          '下次重置',
          '当前流量',
          '重置次数'
        ], $table);
      }
    }

    return [
      'total_found' => $totalUsers,
      'estimated_duration' => $estimatedDuration,
      'estimated_batches' => $estimatedBatches,
    ];
  }
}