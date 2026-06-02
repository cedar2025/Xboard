<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_order', function (Blueprint $table) {
            $table->renameColumn('refund_amount', 'surplus_credit');
        });
    }

    public function down(): void
    {
        Schema::table('v2_order', function (Blueprint $table) {
            $table->renameColumn('surplus_credit', 'refund_amount');
        });
    }
};