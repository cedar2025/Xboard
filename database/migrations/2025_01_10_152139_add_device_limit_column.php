<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->unsignedInteger('device_limit')->nullable()->after('speed_limit');
        });
        Schema::table('v2_user', function (Blueprint $table) {
            $table->integer('device_limit')->nullable()->after('expired_at');
            $table->integer('online_count')->nullable()->after('device_limit');
            $table->timestamp('last_online_at')->nullable()->after('online_count');
        });
    }

    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropColumn('device_limit');
            $table->dropColumn('online_count');
            $table->dropColumn('last_online_at');
        });
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->dropColumn('device_limit');
        });
    }
};
