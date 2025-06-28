<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use App\Models\TrafficResetLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Service for handling traffic reset.
 */
class TrafficResetService
{
  /**
   * Check if a user's traffic should be reset and perform the reset.
   */
  public function checkAndReset(User $user, string $triggerSource = TrafficResetLog::SOURCE_AUTO): bool
  {
    if (!$user->shouldResetTraffic()) {
      return false;
    }

    return $this->performReset($user, $triggerSource);
  }

  /**
   * Perform the traffic reset for a user.
   */
  public function performReset(User $user, string $triggerSource = TrafficResetLog::SOURCE_MANUAL): bool
  {
    try {
      return DB::transaction(function () use ($user, $triggerSource) {
        $oldUpload = $user->u ?? 0;
        $oldDownload = $user->d ?? 0;
        $oldTotal = $oldUpload + $oldDownload;

        $nextResetTime = $this->calculateNextResetTime($user);

        $user->update([
          'u' => 0,
          'd' => 0,
          'last_reset_at' => time(),
          'reset_count' => $user->reset_count + 1,
          'next_reset_at' => $nextResetTime ? $nextResetTime->timestamp : null,
        ]);

        $this->recordResetLog($user, [
          'reset_type' => $this->getResetTypeFromPlan($user->plan),
          'trigger_source' => $triggerSource,
          'old_upload' => $oldUpload,
          'old_download' => $oldDownload,
          'old_total' => $oldTotal,
          'new_upload' => 0,
          'new_download' => 0,
          'new_total' => 0,
        ]);

        $this->clearUserCache($user);

        Log::info(__('traffic_reset.reset_success'), [
          'user_id' => $user->id,
          'email' => $user->email,
          'old_traffic' => $oldTotal,
          'trigger_source' => $triggerSource,
        ]);

        return true;
      });
    } catch (\Exception $e) {
      Log::error(__('traffic_reset.reset_failed'), [
        'user_id' => $user->id,
        'email' => $user->email,
        'error' => $e->getMessage(),
        'trigger_source' => $triggerSource,
      ]);

      return false;
    }
  }

  /**
   * Calculate the next traffic reset time for a user.
   */
  public function calculateNextResetTime(User $user): ?Carbon
  {
    if (
      !$user->plan
      || $user->plan->reset_traffic_method === Plan::RESET_TRAFFIC_NEVER
      || ($user->plan->reset_traffic_method === Plan::RESET_TRAFFIC_FOLLOW_SYSTEM && (int) admin_setting('reset_traffic_method', Plan::RESET_TRAFFIC_MONTHLY) === Plan::RESET_TRAFFIC_NEVER)
      || $user->expired_at === NULL
    ) {
      return null;
    }

    $resetMethod = $user->plan->reset_traffic_method;

    if ($resetMethod === Plan::RESET_TRAFFIC_FOLLOW_SYSTEM) {
      $resetMethod = (int) admin_setting('reset_traffic_method', Plan::RESET_TRAFFIC_MONTHLY);
    }

    $now = Carbon::now();

    return match ($resetMethod) {
      Plan::RESET_TRAFFIC_FIRST_DAY_MONTH => $this->getNextMonthFirstDay($now),
      Plan::RESET_TRAFFIC_MONTHLY => $this->getNextMonthlyReset($user, $now),
      Plan::RESET_TRAFFIC_FIRST_DAY_YEAR => $this->getNextYearFirstDay($now),
      Plan::RESET_TRAFFIC_YEARLY => $this->getNextYearlyReset($user, $now),
      default => null,
    };
  }

  /**
   * Get the first day of the next month.
   */
  private function getNextMonthFirstDay(Carbon $from): Carbon
  {
    return $from->copy()->addMonth()->startOfMonth();
  }

  /**
   * Get the next monthly reset time based on the user's expiration date.
   *
   * Logic:
   * 1. If the user has no expiration date, reset on the 1st of each month.
   * 2. If the user has an expiration date, use the day of that date as the monthly reset day.
   * 3. Prioritize the reset day in the current month if it has not passed yet.
   * 4. Handle cases where the day does not exist in a month (e.g., 31st in February).
   */
  private function getNextMonthlyReset(User $user, Carbon $from): Carbon
  {
    $expiredAt = Carbon::createFromTimestamp($user->expired_at);
    $resetDay = $expiredAt->day;

    return $this->getNextResetByDay($from, $resetDay);
  }

  /**
   * Get the next reset time based on a specific day of the month.
   */
  private function getNextResetByDay(Carbon $from, int $targetDay): Carbon
  {
    $currentMonthTarget = $this->getValidDayInMonth($from->copy(), $targetDay);
    if ($currentMonthTarget->timestamp > $from->timestamp) {
      return $currentMonthTarget;
    }

    $nextMonth = $from->copy()->addMonth();
    return $this->getValidDayInMonth($nextMonth, $targetDay);
  }

  /**
   * Get a valid day in a given month, handling non-existent dates.
   */
  private function getValidDayInMonth(Carbon $month, int $targetDay): Carbon
  {
    $lastDayOfMonth = $month->copy()->endOfMonth()->day;

    if ($targetDay > $lastDayOfMonth) {
      return $month->endOfMonth()->startOfDay();
    }

    return $month->day($targetDay)->startOfDay();
  }

  /**
   * Get the first day of the next year.
   */
  private function getNextYearFirstDay(Carbon $from): Carbon
  {
    return $from->copy()->addYear()->startOfYear();
  }

  /**
   * Get the next yearly reset time based on the user's expiration date.
   *
   * Logic:
   * 1. If the user has no expiration date, reset on January 1st of each year.
   * 2. If the user has an expiration date, use the month and day of that date as the yearly reset date.
   * 3. Prioritize the reset date in the current year if it has not passed yet.
   * 4. Handle the case of February 29th in a leap year.
   */
  private function getNextYearlyReset(User $user, Carbon $from): Carbon
  {
    $expiredAt = Carbon::createFromTimestamp($user->expired_at);

    $currentYearTarget = $this->getValidYearDate($from->copy(), $expiredAt);

    if ($currentYearTarget->timestamp > $from->timestamp) {
      return $currentYearTarget;
    }

    return $this->getValidYearDate($from->copy()->addYear(), $expiredAt);
  }

  /**
   * Get a valid date in a given year, handling leap year cases for Feb 29th.
   */
  private function getValidYearDate(Carbon $year, Carbon $expiredAt): Carbon
  {
    $target = $year->month($expiredAt->month)->day($expiredAt->day)->startOfDay();

    if ($expiredAt->month === 2 && $expiredAt->day === 29 && !$target->isLeapYear()) {
      $target->day(28);
    }

    return $target;
  }

  /**
   * Record the traffic reset log.
   */
  private function recordResetLog(User $user, array $data): void
  {
    TrafficResetLog::create([
      'user_id' => $user->id,
      'reset_type' => $data['reset_type'],
      'reset_time' => now(),
      'old_upload' => $data['old_upload'],
      'old_download' => $data['old_download'],
      'old_total' => $data['old_total'],
      'new_upload' => $data['new_upload'],
      'new_download' => $data['new_download'],
      'new_total' => $data['new_total'],
      'trigger_source' => $data['trigger_source'],
      'metadata' => $data['metadata'] ?? null,
    ]);
  }

  /**
   * Get the reset type from the user's plan.
   */
  private function getResetTypeFromPlan(?Plan $plan): string
  {
    if (!$plan) {
      return TrafficResetLog::TYPE_MANUAL;
    }

    $resetMethod = $plan->reset_traffic_method;

    if ($resetMethod === Plan::RESET_TRAFFIC_FOLLOW_SYSTEM) {
      $resetMethod = (int) admin_setting('reset_traffic_method', Plan::RESET_TRAFFIC_MONTHLY);
    }

    return match ($resetMethod) {
      Plan::RESET_TRAFFIC_FIRST_DAY_MONTH => TrafficResetLog::TYPE_FIRST_DAY_MONTH,
      Plan::RESET_TRAFFIC_MONTHLY => TrafficResetLog::TYPE_MONTHLY,
      Plan::RESET_TRAFFIC_FIRST_DAY_YEAR => TrafficResetLog::TYPE_FIRST_DAY_YEAR,
      Plan::RESET_TRAFFIC_YEARLY => TrafficResetLog::TYPE_YEARLY,
      Plan::RESET_TRAFFIC_NEVER => TrafficResetLog::TYPE_MANUAL,
      default => TrafficResetLog::TYPE_MANUAL,
    };
  }

  /**
   * Clear user-related cache.
   */
  private function clearUserCache(User $user): void
  {
    $cacheKeys = [
      "user_traffic_{$user->id}",
      "user_reset_status_{$user->id}",
      "user_subscription_{$user->token}",
    ];

    foreach ($cacheKeys as $key) {
      Cache::forget($key);
    }
  }

  /**
   * Batch check and reset users. Processes all eligible users in batches.
   */
  public function batchCheckReset(int $batchSize = 100, ?callable $progressCallback = null): array
  {
    $startTime = microtime(true);
    $totalResetCount = 0;
    $totalProcessedCount = 0;
    $batchNumber = 1;
    $errors = [];
    $lastProcessedId = 0;

    Log::info('Starting batch traffic reset task.', [
      'batch_size' => $batchSize,
      'start_time' => now()->toDateTimeString(),
    ]);

    try {
      do {
        $users = User::where('next_reset_at', '<=', time())
          ->whereNotNull('next_reset_at')
          ->where('id', '>', $lastProcessedId)
          ->where(function ($query) {
            $query->where('expired_at', '>', time())
              ->orWhereNull('expired_at');
          })
          ->where('banned', 0)
          ->whereNotNull('plan_id')
          ->orderBy('id')
          ->limit($batchSize)
          ->get();

        if ($users->isEmpty()) {
          break;
        }

        $batchStartTime = microtime(true);
        $batchResetCount = 0;
        $batchErrors = [];

        if ($progressCallback) {
          $progressCallback([
            'batch_number' => $batchNumber,
            'batch_size' => $users->count(),
            'total_processed' => $totalProcessedCount,
          ]);
        }

        Log::info("Processing batch #{$batchNumber}", [
          'batch_number' => $batchNumber,
          'batch_size' => $users->count(),
          'total_processed' => $totalProcessedCount,
          'id_range' => $users->first()->id . '-' . $users->last()->id,
        ]);

        foreach ($users as $user) {
          try {
            if ($this->checkAndReset($user, TrafficResetLog::SOURCE_CRON)) {
              $batchResetCount++;
              $totalResetCount++;
            }
            $totalProcessedCount++;
            $lastProcessedId = $user->id;
          } catch (\Exception $e) {
            $error = [
              'user_id' => $user->id,
              'email' => $user->email,
              'error' => $e->getMessage(),
              'batch' => $batchNumber,
              'timestamp' => now()->toDateTimeString(),
            ];
            $batchErrors[] = $error;
            $errors[] = $error;

            Log::error('User traffic reset failed', $error);

            $totalProcessedCount++;
            $lastProcessedId = $user->id;
          }
        }

        $batchDuration = round(microtime(true) - $batchStartTime, 2);

        Log::info("Batch #{$batchNumber} processing complete", [
          'batch_number' => $batchNumber,
          'processed_count' => $users->count(),
          'reset_count' => $batchResetCount,
          'error_count' => count($batchErrors),
          'duration' => $batchDuration,
          'last_processed_id' => $lastProcessedId,
        ]);

        $batchNumber++;

        if ($batchNumber % 10 === 0) {
          gc_collect_cycles();
        }

        if ($batchNumber % 5 === 0) {
          usleep(100000);
        }

      } while (true);

    } catch (\Exception $e) {
      Log::error('Batch traffic reset task failed with an exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'total_processed' => $totalProcessedCount,
        'total_reset' => $totalResetCount,
        'last_processed_id' => $lastProcessedId,
      ]);

      $errors[] = [
        'type' => 'system_error',
        'error' => $e->getMessage(),
        'batch' => $batchNumber,
        'last_processed_id' => $lastProcessedId,
        'timestamp' => now()->toDateTimeString(),
      ];
    }

    $totalDuration = round(microtime(true) - $startTime, 2);

    $result = [
      'total_processed' => $totalProcessedCount,
      'total_reset' => $totalResetCount,
      'total_batches' => $batchNumber - 1,
      'error_count' => count($errors),
      'errors' => $errors,
      'duration' => $totalDuration,
      'batch_size' => $batchSize,
      'last_processed_id' => $lastProcessedId,
      'completed_at' => now()->toDateTimeString(),
    ];

    Log::info('Batch traffic reset task completed', $result);

    return $result;
  }

  /**
   * Set the initial reset time for a new user.
   */
  public function setInitialResetTime(User $user): void
  {
    if ($user->next_reset_at !== null) {
      return;
    }

    $nextResetTime = $this->calculateNextResetTime($user);

    if ($nextResetTime) {
      $user->update(['next_reset_at' => $nextResetTime->timestamp]);
    }
  }

  /**
   * Get the user's traffic reset history.
   */
  public function getUserResetHistory(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
  {
    return $user->trafficResetLogs()
      ->orderBy('reset_time', 'desc')
      ->limit($limit)
      ->get();
  }

  /**
   * Check if the user is eligible for traffic reset.
   */
  public function canReset(User $user): bool
  {
    return $user->isActive() && $user->plan !== null;
  }

  /**
   * Manually reset a user's traffic (Admin function).
   */
  public function manualReset(User $user, array $metadata = []): bool
  {
    if (!$this->canReset($user)) {
      return false;
    }

    return $this->performReset($user, TrafficResetLog::SOURCE_MANUAL);
  }
}