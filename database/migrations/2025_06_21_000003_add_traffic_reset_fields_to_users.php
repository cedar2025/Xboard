<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Services\TrafficResetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddTrafficResetFieldsToUsers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ini_set('memory_limit', '-1');
        if (!Schema::hasColumn('v2_user', 'next_reset_at')) {
            Schema::table('v2_user', function (Blueprint $table) {
                $table->integer('next_reset_at')->nullable()->after('expired_at')->comment('下次流量重置时间');
                $table->integer('last_reset_at')->nullable()->after('next_reset_at')->comment('上次流量重置时间');
                $table->integer('reset_count')->default(0)->after('last_reset_at')->comment('流量重置次数');
                $table->index('next_reset_at', 'idx_next_reset_at');
            });
        }

        // 为现有用户设置初始重置时间
        $this->migrateExistingUsers();
    }

    /**
     * 为现有用户迁移流量重置数据
     */
    private function migrateExistingUsers(): void
    {
        try {
            // 获取所有需要迁移的用户ID，避免查询条件变化
            $userIds = User::whereNotNull('plan_id')
                ->where('banned', 0)
                ->whereNull('next_reset_at')
                ->pluck('id')
                ->toArray();

            $totalUsers = count($userIds);
            if ($totalUsers === 0) {
                return;
            }

            echo "开始迁移 {$totalUsers} 个用户的流量重置数据...\n";
            $trafficResetService = app(TrafficResetService::class);
            $processedCount = 0;
            $failedCount = 0;

            // 分批处理用户ID
            $chunks = array_chunk($userIds, 200);

            foreach ($chunks as $chunkIds) {
                $users = User::whereIn('id', $chunkIds)
                    ->with('plan:id,reset_traffic_method')
                    ->get();

                foreach ($users as $user) {
                    try {
                        $trafficResetService->setInitialResetTime($user);
                        $processedCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        Log::error('迁移用户流量重置时间失败', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // 每 100 个用户显示一次进度
                    if (($processedCount + $failedCount) % 100 === 0 || ($processedCount + $failedCount) === $totalUsers) {
                        $currentTotal = $processedCount + $failedCount;
                        $percentage = round(($currentTotal / $totalUsers) * 100, 1);
                        echo "进度: {$currentTotal}/{$totalUsers} ({$percentage}%) [成功: {$processedCount}, 失败: {$failedCount}]\n";
                    }
                }
            }

            echo "迁移完成！总计 {$totalUsers} 个用户，成功: {$processedCount}，失败: {$failedCount}\n";
        } catch (\Exception $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropIndex('idx_next_reset_at');
            $table->dropColumn(['next_reset_at', 'last_reset_at', 'reset_count']);
        });
    }
}