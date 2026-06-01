<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert password_algo and password_salt columns from CHAR(10) to VARCHAR(10).
 *
 * MySQL CHAR(10) right-pads stored values with spaces. The verification helper
 * used strict equality for algo matching, so legacy v2board users with algo values
 * shorter than 10 chars (md5=3, sha256=6, md5salt=7) could never log in.
 *
 * This migration:
 *   1. Changes both columns to VARCHAR(10) to stop the padding.
 *   2. Runs a raw-SQL TRIM() backfill to clean any already-padded rows.
 *
 * Columns affected: v2_user.password_algo, v2_user.password_salt
 *
 * NOTE on down(): Reverting to CHAR(10) reintroduces the v2board sha256/md5 login
 * bug. Do not run migrate:rollback in production without a follow-up data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columnType = Schema::getColumnType('v2_user', 'password_algo');

        if (in_array($columnType, ['string', 'varchar'], true)) {
            return;
        }

        Schema::table('v2_user', function (Blueprint $table) {
            $table->string('password_algo', 10)->nullable()->change();
            $table->string('password_salt', 10)->nullable()->change();
        });

        // Raw-SQL TRIM backfill — bypasses Eloquent events and Octane model cache
        DB::statement("UPDATE v2_user SET password_algo = TRIM(password_algo) WHERE password_algo IS NOT NULL");
        DB::statement("UPDATE v2_user SET password_salt = TRIM(password_salt) WHERE password_salt IS NOT NULL");
    }

    /**
     * WARNING: Reverting to CHAR(10) reintroduces the v2board sha256/md5 login bug;
     * do not run in production without a follow-up data migration.
     */
    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->char('password_algo', 10)->nullable()->change();
            $table->char('password_salt', 10)->nullable()->change();
        });
    }
};
