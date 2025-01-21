<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('v2_order')->where('commission_status', null)->update([
            'commission_status' => 0
        ]);
        Schema::table('v2_order', function (Blueprint $table) {
            $table->integer('commission_status')->default(value: 0)->comment('0待确认1发放中2有效3无效')->change();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_order', function (Blueprint $table) {
            $table->integer('commission_status')->nullable()->comment('0待确认1发放中2有效3无效')->change();
        });
    }
};
