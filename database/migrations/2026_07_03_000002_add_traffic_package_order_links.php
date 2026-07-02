<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_order', function (Blueprint $table) {
            if (Schema::hasColumn('v2_order', 'plan_id')) {
                $table->integer('plan_id')->nullable()->change();
            }
            if (!Schema::hasColumn('v2_order', 'traffic_package_id')) {
                $table->integer('traffic_package_id')->nullable()->after('plan_id')->index('idx_order_traffic_package');
            }
        });

        Schema::table('v2_user_traffic_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('v2_user_traffic_packages', 'traffic_package_id')) {
                $table->integer('traffic_package_id')->nullable()->after('plan_id')->index('idx_user_package_catalog');
            }
            if (Schema::hasColumn('v2_user_traffic_packages', 'plan_id')) {
                $table->integer('plan_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('v2_user_traffic_packages', function (Blueprint $table) {
            if (Schema::hasColumn('v2_user_traffic_packages', 'traffic_package_id')) {
                $table->dropColumn('traffic_package_id');
            }
        });

        Schema::table('v2_order', function (Blueprint $table) {
            if (Schema::hasColumn('v2_order', 'traffic_package_id')) {
                $table->dropColumn('traffic_package_id');
            }
        });
    }
};
