<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Ensure invite codes are unique.
     *
     * Existing duplicates (if any) keep the oldest row per code so registration
     * lookups that use first() stay consistent with historical attribution.
     */
    public function up(): void
    {
        if (!Schema::hasTable('v2_invite_code')) {
            return;
        }

        // MySQL / MariaDB: drop duplicates, keep MIN(id) per code
        DB::statement('
            DELETE c FROM v2_invite_code c
            INNER JOIN (
                SELECT code, MIN(id) AS keep_id
                FROM v2_invite_code
                GROUP BY code
            ) k ON c.code = k.code AND c.id <> k.keep_id
        ');

        Schema::table('v2_invite_code', function (Blueprint $table) {
            if (!Schema::hasIndex('v2_invite_code', 'uk_invite_code')) {
                $table->unique('code', 'uk_invite_code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('v2_invite_code')) {
            return;
        }

        Schema::table('v2_invite_code', function (Blueprint $table) {
            if (Schema::hasIndex('v2_invite_code', 'uk_invite_code')) {
                $table->dropUnique('uk_invite_code');
            }
        });
    }
};
