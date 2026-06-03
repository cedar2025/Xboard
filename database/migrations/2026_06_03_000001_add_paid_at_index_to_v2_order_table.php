<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasIndex('v2_order', ['paid_at'])) {
            return;
        }

        Schema::table('v2_order', function (Blueprint $table) {
            $table->index('paid_at', 'idx_v2_order_paid_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasIndex('v2_order', 'idx_v2_order_paid_at')) {
            return;
        }

        Schema::table('v2_order', function (Blueprint $table) {
            $table->dropIndex('idx_v2_order_paid_at');
        });
    }
};
