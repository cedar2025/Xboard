<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates the unique constraint on v2_stat_user to include server_id,
     * allowing per-node user traffic tracking.
     */
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            // SQLite uses explicit WHERE queries in code, no constraint changes needed
            return;
        }

        Schema::table('v2_stat_user', function (Blueprint $table) {
            // Drop the old unique constraint that doesn't include server_id
            $table->dropUnique('server_rate_user_id_record_at');
            
            // Add new unique constraint including server_id
            // Note: NULL server_id values (legacy) are treated as distinct in MySQL
            $table->unique(
                ['user_id', 'server_id', 'server_rate', 'record_at', 'record_type'],
                'stat_user_unique_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('v2_stat_user', function (Blueprint $table) {
            // Drop new constraint
            $table->dropUnique('stat_user_unique_idx');
            
            // Restore original constraint
            $table->unique(
                ['server_rate', 'user_id', 'record_at'],
                'server_rate_user_id_record_at'
            );
        });
    }
};
