<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\TrafficResetLog;
use App\Services\TrafficResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetTraffic extends Command
{
  protected $signature = 'reset:traffic {--fix-null : 修正模式，重新计算next_reset_at为null的用户} {--force : 强制模式，重新计算所有用户的重置时间}';

  protected $description = '流量重置 - 处理所有需要重置的用户';

  public function __construct(
    private readonly TrafficResetService $trafficResetService
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $fixNull = $this->option('fix-null');
    $force = $this->option('force');

    $this->info('🚀 开始执行流量重置任务...');

    if ($fixNull) {
      $this->warn('🔧 修正模式 - 将重新计算next_reset_at为null的用户');
    } elseif ($force) {
      $this->warn('⚡ 强制模式 - 将重新计算所有用户的重置时间');
    }

    try {
      $result = $fixNull ? $this->performFix() : ($force ? $this->performForce() : $this->performReset());
      $this->displayResults($result, $fixNull || $force);
      return self::SUCCESS;

    } catch (\Exception $e) {
      $this->error("❌ 任务执行失败: {$e->getMessage()}");

      Log::error('流量重置命令执行失败', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return self::FAILURE;
    }
  }

  private function displayResults(array $result, bool $isSpecialMode): void
  {
    $this->info("✅ 任务完成！\n");

    if ($isSpecialMode) {
      $this->displayFixResults($result);
    } else {
      $this->displayExecutionResults($result);
    }
  }

  private function displayFixResults(array $result): void
  {
    $this->info("📊 修正结果统计:");
    $this->info("🔍 发现用户总数: {$result['total_found']}");
    $this->info("✅ 成功修正数量: {$result['total_fixed']}");
    $this->info("⏱️  总执行时间: {$result['duration']} 秒");

    if ($result['error_count'] > 0) {
      $this->warn("⚠️  错误数量: {$result['error_count']}");
      $this->warn("详细错误信息请查看日志");
    } else {
      $this->info("✨ 无错误发生");
    }

    if ($result['total_found'] > 0) {
      $avgTime = round($result['duration'] / $result['total_found'], 4);
      $this->info("⚡ 平均处理速度: {$avgTime} 秒/用户");
    }
  }



  private function displayExecutionResults(array $result): void
  {
    $this->info("📊 执行结果统计:");
    $this->info("👥 处理用户总数: {$result['total_processed']}");
    $this->info("🔄 重置用户数量: {$result['total_reset']}");
    $this->info("⏱️  总执行时间: {$result['duration']} 秒");

    if ($result['error_count'] > 0) {
      $this->warn("⚠️  错误数量: {$result['error_count']}");
      $this->warn("详细错误信息请查看日志");
    } else {
      $this->info("✨ 无错误发生");
    }

    if ($result['total_processed'] > 0) {
      $avgTime = round($result['duration'] / $result['total_processed'], 4);
      $this->info("⚡ 平均处理速度: {$avgTime} 秒/用户");
    }
  }

  private function performReset(): array
  {
    $startTime = microtime(true);
    $totalResetCount = 0;
    $errors = [];

    $users = $this->getResetQuery()->get();

    if ($users->isEmpty()) {
      $this->info("😴 当前没有需要重置的用户");
      return [
        'total_processed' => 0,
        'total_reset' => 0,
        'error_count' => 0,
        'duration' => round(microtime(true) - $startTime, 2),
      ];
    }

    $this->info("找到 {$users->count()} 个需要重置的用户");

    foreach ($users as $user) {
      try {
        $totalResetCount += (int) $this->trafficResetService->checkAndReset($user, TrafficResetLog::SOURCE_CRON);
      } catch (\Exception $e) {
        $errors[] = [
          'user_id' => $user->id,
          'email' => $user->email,
          'error' => $e->getMessage(),
        ];
        Log::error('用户流量重置失败', [
          'user_id' => $user->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return [
      'total_processed' => $users->count(),
      'total_reset' => $totalResetCount,
      'error_count' => count($errors),
      'duration' => round(microtime(true) - $startTime, 2),
    ];
  }

  private function performFix(): array
  {
    $startTime = microtime(true);
    $nullUsers = $this->getNullResetTimeUsers();

    if ($nullUsers->isEmpty()) {
      $this->info("✅ 没有发现next_reset_at为null的用户");
      return [
        'total_found' => 0,
        'total_fixed' => 0,
        'error_count' => 0,
        'duration' => round(microtime(true) - $startTime, 2),
      ];
    }

    $this->info("🔧 发现 {$nullUsers->count()} 个next_reset_at为null的用户，开始修正...");

    $fixedCount = 0;
    $errors = [];

    foreach ($nullUsers as $user) {
      try {
        $nextResetTime = $this->trafficResetService->calculateNextResetTime($user);
        if ($nextResetTime) {
          $user->next_reset_at = $nextResetTime->timestamp;
          $user->save();
          $fixedCount++;
        }
      } catch (\Exception $e) {
        $errors[] = [
          'user_id' => $user->id,
          'email' => $user->email,
          'error' => $e->getMessage(),
        ];
        Log::error('修正用户next_reset_at失败', [
          'user_id' => $user->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return [
      'total_found' => $nullUsers->count(),
      'total_fixed' => $fixedCount,
      'error_count' => count($errors),
      'duration' => round(microtime(true) - $startTime, 2),
    ];
  }

  private function performForce(): array
  {
    $startTime = microtime(true);
    $allUsers = $this->getAllUsers();

    if ($allUsers->isEmpty()) {
      $this->info("✅ 没有发现需要处理的用户");
      return [
        'total_found' => 0,
        'total_fixed' => 0,
        'error_count' => 0,
        'duration' => round(microtime(true) - $startTime, 2),
      ];
    }

    $this->info("⚡ 发现 {$allUsers->count()} 个用户，开始重新计算重置时间...");

    $fixedCount = 0;
    $errors = [];

    foreach ($allUsers as $user) {
      try {
        $nextResetTime = $this->trafficResetService->calculateNextResetTime($user);
        if ($nextResetTime) {
          $user->next_reset_at = $nextResetTime->timestamp;
          $user->save();
          $fixedCount++;
        }
      } catch (\Exception $e) {
        $errors[] = [
          'user_id' => $user->id,
          'email' => $user->email,
          'error' => $e->getMessage(),
        ];
        Log::error('强制重新计算用户next_reset_at失败', [
          'user_id' => $user->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return [
      'total_found' => $allUsers->count(),
      'total_fixed' => $fixedCount,
      'error_count' => count($errors),
      'duration' => round(microtime(true) - $startTime, 2),
    ];
  }



  private function getResetQuery()
  {
    return User::where('next_reset_at', '<=', time())
      ->whereNotNull('next_reset_at')
      ->where(function ($query) {
        $query->where('expired_at', '>', time())
          ->orWhereNull('expired_at');
      })
      ->where('banned', false)
      ->whereNotNull('plan_id');
  }



  private function getNullResetTimeUsers()
  {
    return User::whereNull('next_reset_at')
      ->whereNotNull('plan_id')
      ->where(function ($query) {
        $query->where('expired_at', '>', time())
          ->orWhereNull('expired_at');
      })
      ->where('banned', false)
      ->with('plan:id,name,reset_traffic_method')
      ->get();
  }

  private function getAllUsers()
  {
    return User::whereNotNull('plan_id')
      ->where(function ($query) {
        $query->where('expired_at', '>', time())
          ->orWhereNull('expired_at');
      })
      ->where('banned', false)
      ->with('plan:id,name,reset_traffic_method')
      ->get();
  }

}