<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * 旧的价格字段到新周期的映射关系
     */
    private const PERIOD_MAPPING = [
        'month_price' => 'monthly',
        'quarter_price' => 'quarterly',
        'half_year_price' => 'half_yearly',
        'year_price' => 'yearly',
        'two_year_price' => 'two_yearly',
        'three_year_price' => 'three_yearly',
        'onetime_price' => 'onetime',
        'reset_price' => 'reset_traffic'
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 批量更新订单的周期字段
        foreach (self::PERIOD_MAPPING as $oldPeriod => $newPeriod) {
            DB::table('v2_order')
                ->where('period', $oldPeriod)
                ->update(['period' => $newPeriod]);
        }

        // 检查是否还有未转换的记录
        $unconvertedCount = DB::table('v2_order')
            ->whereNotIn('period', array_values(self::PERIOD_MAPPING))
            ->count();

        if ($unconvertedCount > 0) {
            Log::warning("Found {$unconvertedCount} orders with unconverted period values");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚操作 - 将新的周期值转换回旧的价格字段名
        foreach (self::PERIOD_MAPPING as $oldPeriod => $newPeriod) {
            DB::table('v2_order')
                ->where('period', $newPeriod)
                ->update(['period' => $oldPeriod]);
        }
    }
};