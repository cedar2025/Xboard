<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->index('t');
            $table->index('online_count');
            $table->index('created_at');
        });

        Schema::table('v2_order', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('status');
            $table->index('total_amount');
            $table->index('commission_status');
            $table->index('invite_user_id');
            $table->index('commission_balance');
        });

        Schema::table('v2_stat_server', function (Blueprint $table) {
            $table->index('server_id');
            $table->index('record_at');
            $table->index('u');
            $table->index('d');
        });

        Schema::table('v2_stat_user', function (Blueprint $table) {
            $table->index('u');
            $table->index('d');
        });

        Schema::table('v2_commission_log', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('get_amount');
        });

        Schema::table('v2_ticket', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropIndex(['t']);
            $table->dropIndex(['online_count']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('v2_order', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['total_amount']);
            $table->dropIndex(['commission_status']);
            $table->dropIndex(['invite_user_id']);
            $table->dropIndex(['commission_balance']);
        });

        Schema::table('v2_stat_server', function (Blueprint $table) {
            $table->dropIndex(['server_id']);
            $table->dropIndex(['record_at']);
            $table->dropIndex(['u']);
            $table->dropIndex(['d']);
        });

        Schema::table('v2_stat_user', function (Blueprint $table) {
            $table->dropIndex(['u']);
            $table->dropIndex(['d']);
        });

        Schema::table('v2_commission_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['get_amount']);
        });

        Schema::table('v2_ticket', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });
    }
};