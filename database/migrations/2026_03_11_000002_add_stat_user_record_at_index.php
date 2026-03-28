<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_stat_user', function (Blueprint $table) {
            $table->index(['record_at', 'user_id'], 'idx_stat_user_record_user');
        });
    }

    public function down(): void
    {
        Schema::table('v2_stat_user', function (Blueprint $table) {
            $table->dropIndex('idx_stat_user_record_user');
        });
    }
};
