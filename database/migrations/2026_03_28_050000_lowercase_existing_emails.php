<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 统计需要转换的记录数
        $count = DB::table('v2_user')
            ->whereNotNull('email')
            ->whereRaw('email != LOWER(email)')
            ->count();

        if ($count > 0) {
            Log::info("Converting {$count} email(s) to lowercase");
            DB::table('v2_user')
                ->whereNotNull('email')
                ->whereRaw('email != LOWER(email)')
                ->update(['email' => DB::raw('LOWER(email)')]);
                
            Log::info("Email lowercase conversion completed");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 无法恢复原始大小写
    }
};
