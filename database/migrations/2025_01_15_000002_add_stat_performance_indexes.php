<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Safely add an index only if it doesn't already exist.
     */
    private function addIndexIfNotExists(Blueprint $table, string $column): void
    {
        $tableName = $table->getTable();
        $indexName = "{$tableName}_{$column}_index";
        if (!Schema::hasIndex($tableName, $indexName)) {
            $table->index($column);
        }
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $this->addIndexIfNotExists($table, 't');
            $this->addIndexIfNotExists($table, 'online_count');
            $this->addIndexIfNotExists($table, 'created_at');
        });

        Schema::table('v2_order', function (Blueprint $table) {
            $this->addIndexIfNotExists($table, 'created_at');
            $this->addIndexIfNotExists($table, 'status');
            $this->addIndexIfNotExists($table, 'total_amount');
            $this->addIndexIfNotExists($table, 'commission_status');
            $this->addIndexIfNotExists($table, 'invite_user_id');
            $this->addIndexIfNotExists($table, 'commission_balance');
        });

        Schema::table('v2_stat_server', function (Blueprint $table) {
            $this->addIndexIfNotExists($table, 'server_id');
            $this->addIndexIfNotExists($table, 'record_at');
            $this->addIndexIfNotExists($table, 'u');
            $this->addIndexIfNotExists($table, 'd');
        });

        Schema::table('v2_stat_user', function (Blueprint $table) {
            $this->addIndexIfNotExists($table, 'u');
            $this->addIndexIfNotExists($table, 'd');
        });

        Schema::table('v2_commission_log', function (Blueprint $table) {
            $this->addIndexIfNotExists($table, 'created_at');
            $this->addIndexIfNotExists($table, 'get_amount');
        });

        Schema::table('v2_ticket', function (Blueprint $table) {
            $this->addIndexIfNotExists($table, 'status');
            $this->addIndexIfNotExists($table, 'created_at');
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