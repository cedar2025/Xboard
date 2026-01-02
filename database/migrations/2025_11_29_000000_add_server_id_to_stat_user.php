<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('v2_stat_user', function (Blueprint $table) {
            // Add server_id column as nullable for backward compatibility
            $table->integer('server_id')->nullable()->after('user_id')->comment('节点ID (nullable for legacy data)');
            
            // Add index for per-node queries
            if (config('database.default') !== 'sqlite') {
                $table->index(['user_id', 'server_id', 'record_at'], 'user_server_record_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_stat_user', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropIndex('user_server_record_idx');
            }
            $table->dropColumn('server_id');
        });
    }
};
