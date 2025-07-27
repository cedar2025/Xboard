<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add new columns first
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->json('prices')->nullable()->after('name')
                ->comment('Store different duration prices and reset traffic price');
            $table->boolean('sell')->default(false)->after('prices')->comment('is sell');
        });

        // Step 2: Migrate data to new format
        DB::table('v2_plan')->orderBy('id')->chunk(100, function ($plans) {
            foreach ($plans as $plan) {
                $prices = array_filter([
                    'monthly' => $plan->month_price !== null ? $plan->month_price / 100 : null,
                    'quarterly' => $plan->quarter_price !== null ? $plan->quarter_price / 100 : null,
                    'half_yearly' => $plan->half_year_price !== null ? $plan->half_year_price / 100 : null,
                    'yearly' => $plan->year_price !== null ? $plan->year_price / 100 : null,
                    'two_yearly' => $plan->two_year_price !== null ? $plan->two_year_price / 100 : null,
                    'three_yearly' => $plan->three_year_price !== null ? $plan->three_year_price / 100 : null,
                    'onetime' => $plan->onetime_price !== null ? $plan->onetime_price / 100 : null,
                    'reset_traffic' => $plan->reset_price !== null ? $plan->reset_price / 100 : null
                ], function ($price) {
                    return $price !== null;
                });

                DB::table('v2_plan')
                    ->where('id', $plan->id)
                    ->update([
                        'prices' => json_encode($prices),
                        'sell' => $plan->show
                    ]);
            }
        });

        // Step 3: Optimize existing columns
        Schema::table('v2_plan', function (Blueprint $table) {
            // Modify existing columns to be more efficient
            $table->unsignedInteger('group_id')->nullable()->change();
            $table->unsignedBigInteger('transfer_enable')->nullable()
                ->comment('Transfer limit in bytes')->change();
            $table->unsignedInteger('speed_limit')->nullable()
                ->comment('Speed limit in Mbps, 0 for unlimited')->change();
            $table->integer('reset_traffic_method')->nullable()->default(0)
                ->comment('重置流量方式:null跟随系统设置、0每月1号、1按月重置、2不重置、3每年1月1日、4按年重置')->change();
            $table->unsignedInteger('capacity_limit')->nullable()->default(0)
                ->comment('0 for unlimited')->change();
        });

        // Step 4: Drop old columns
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->dropColumn([
                'month_price',
                'quarter_price',
                'half_year_price',
                'year_price',
                'two_year_price',
                'three_year_price',
                'onetime_price',
                'reset_price',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add back old columns
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->integer('month_price')->nullable();
            $table->integer('quarter_price')->nullable();
            $table->integer('half_year_price')->nullable();
            $table->integer('year_price')->nullable();
            $table->integer('two_year_price')->nullable();
            $table->integer('three_year_price')->nullable();
            $table->integer('onetime_price')->nullable();
            $table->integer('reset_price')->nullable();
        });

        // Step 2: Restore data from new format to old format
        DB::table('v2_plan')->orderBy('id')->chunk(100, function ($plans) {
            foreach ($plans as $plan) {
                $prices = json_decode($plan->prices, true) ?? [];

                DB::table('v2_plan')
                    ->where('id', $plan->id)
                    ->update([
                        'month_price' => $prices['monthly'] * 100 ?? null,
                        'quarter_price' => $prices['quarterly'] * 100 ?? null,
                        'half_year_price' => $prices['half_yearly'] * 100 ?? null,
                        'year_price' => $prices['yearly'] * 100 ?? null,
                        'two_year_price' => $prices['two_yearly'] * 100 ?? null,
                        'three_year_price' => $prices['three_yearly'] * 100 ?? null,
                        'onetime_price' => $prices['onetime'] * 100 ?? null,
                        'reset_price' => $prices['reset_traffic'] * 100 ?? null,
                    ]);
            }
        });

        // Step 3: Drop new columns
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->dropColumn([
                'prices',
                'sell'
            ]);
        });

        // Step 4: Restore column types to original
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->integer('group_id')->change();
            $table->integer('transfer_enable')->change();
            $table->integer('speed_limit')->nullable()->change();
            $table->integer('reset_traffic_method')->nullable()->change();
            $table->integer('capacity_limit')->nullable()->change();
        });
    }
};