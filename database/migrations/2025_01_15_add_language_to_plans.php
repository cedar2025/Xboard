<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->char('language', 5)->nullable()->after('name')->comment('Language code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
}; 