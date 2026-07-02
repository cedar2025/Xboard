<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_traffic_packages')) {
            Schema::create('v2_traffic_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('transfer_enable');
                $table->decimal('price', 10, 2);
                $table->integer('group_id')->nullable();
                $table->integer('speed_limit')->nullable();
                $table->integer('device_limit')->nullable();
                $table->boolean('show')->default(false);
                $table->boolean('sell')->default(false);
                $table->integer('sort')->nullable();
                $table->text('content')->nullable();
                $table->integer('created_at');
                $table->integer('updated_at');

                $table->index(['show', 'sell', 'sort'], 'idx_traffic_package_sale');
            });
        }

        $now = time();
        DB::table('v2_plan')->orderBy('id')->chunk(100, function ($plans) use ($now) {
            foreach ($plans as $plan) {
                $prices = json_decode((string) $plan->prices, true) ?: [];
                $hasOnetimePrice = isset($prices[Plan::PERIOD_ONETIME]) && (float) $prices[Plan::PERIOD_ONETIME] > 0;
                $hasStandardPrice = collect([
                    Plan::PERIOD_MONTHLY,
                    Plan::PERIOD_QUARTERLY,
                    Plan::PERIOD_HALF_YEARLY,
                    Plan::PERIOD_YEARLY,
                    Plan::PERIOD_TWO_YEARLY,
                    Plan::PERIOD_THREE_YEARLY,
                ])->contains(fn(string $period): bool => isset($prices[$period]) && (float) $prices[$period] > 0);

                if (!$hasOnetimePrice || $hasStandardPrice) {
                    continue;
                }

                $exists = DB::table('v2_traffic_packages')
                    ->where('name', $plan->name)
                    ->where('transfer_enable', $plan->transfer_enable)
                    ->where('price', (float) $prices[Plan::PERIOD_ONETIME])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('v2_traffic_packages')->insert([
                    'name' => $plan->name,
                    'transfer_enable' => $plan->transfer_enable,
                    'price' => (float) $prices[Plan::PERIOD_ONETIME],
                    'group_id' => $plan->group_id,
                    'speed_limit' => $plan->speed_limit,
                    'device_limit' => $plan->device_limit,
                    'show' => (bool) $plan->show,
                    'sell' => (bool) $plan->sell,
                    'sort' => $plan->sort,
                    'content' => $plan->content,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_traffic_packages');
    }
};
