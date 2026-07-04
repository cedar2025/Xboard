<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_log', function (Blueprint $table) {
            $table->index('level', 'v2_log_level_index');
        });
    }

    public function down(): void
    {
        Schema::table('v2_log', function (Blueprint $table) {
            $table->dropIndex('v2_log_level_index');
        });
    }
};
