<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add last_reply_user_id column if not exists
        if (!Schema::hasColumn('v2_ticket', 'last_reply_user_id')) {
            Schema::table('v2_ticket', function (Blueprint $table) {
                $table->integer('last_reply_user_id')->nullable()->after('reply_status');
            });
        }

        // Fix reply_status semantics: swap 0 and 1
        // Old: 0=admin replied, 1=user replied (inverted)
        // New: 0=待回复(waiting), 1=已回复(replied) — matches frontend expectations
        DB::table('v2_ticket')
            ->whereIn('reply_status', [0, 1])
            ->update([
                'reply_status' => DB::raw("CASE WHEN reply_status = 0 THEN 1 WHEN reply_status = 1 THEN 0 END")
            ]);

        // Fix default: new tickets should be "待回复" (0), not "已回复" (1)
        Schema::table('v2_ticket', function (Blueprint $table) {
            $table->integer('reply_status')->default(0)->comment('0:待回复 1:已回复')->change();
        });
    }

    public function down(): void
    {
        // Reverse the swap
        DB::table('v2_ticket')
            ->whereIn('reply_status', [0, 1])
            ->update([
                'reply_status' => DB::raw("CASE WHEN reply_status = 0 THEN 1 WHEN reply_status = 1 THEN 0 END")
            ]);

        Schema::table('v2_ticket', function (Blueprint $table) {
            $table->integer('reply_status')->default(1)->comment('0:待回复 1:已回复')->change();
        });

        // Note: last_reply_user_id column is intentionally kept to avoid dropping
        // a column that may have existed before this migration.
    }
};
