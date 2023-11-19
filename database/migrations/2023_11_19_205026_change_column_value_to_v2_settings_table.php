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
        Schema::table('v2_settings', function (Blueprint $table) {
            $table->text('value')->comment('设置值')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_settings', function (Blueprint $table) {
            $table->string('value')->comment('设置值')->nullable()->change();
        });
    }
};
