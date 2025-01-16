<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE v2_user MODIFY email VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        DB::statement('ALTER TABLE v2_user DROP INDEX email');
        DB::statement('ALTER TABLE v2_user ADD UNIQUE INDEX email (email)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE v2_user MODIFY email VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        DB::statement('ALTER TABLE v2_user DROP INDEX email');
        DB::statement('ALTER TABLE v2_user ADD UNIQUE INDEX email (email)');
    }
}; 