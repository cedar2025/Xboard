<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add `last_plan_period` column to `v2_user` table.
     */
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->string('last_plan_period')->nullable()->comment('最后一个订阅周期')->after('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropColumn('last_plan_period');
        });
    }
};
