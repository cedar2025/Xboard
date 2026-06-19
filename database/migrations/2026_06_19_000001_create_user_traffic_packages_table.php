<?php

use App\Models\UserTrafficPackage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('v2_user_traffic_packages')) {
            return;
        }

        Schema::create('v2_user_traffic_packages', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('order_id')->nullable();
            $table->integer('plan_id');
            $table->bigInteger('total_bytes')->default(0);
            $table->bigInteger('remaining_bytes')->default(0);
            $table->string('status', 16)->default(UserTrafficPackage::STATUS_ACTIVE);
            $table->integer('depleted_at')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->index(['user_id', 'status', 'remaining_bytes'], 'idx_user_package_balance');
            $table->index('order_id', 'idx_user_package_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_user_traffic_packages');
    }
};
