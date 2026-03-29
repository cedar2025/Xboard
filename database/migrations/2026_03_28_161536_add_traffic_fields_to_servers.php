<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('v2_server', function (Blueprint $table) {
            if (!Schema::hasColumn('v2_server', 'transfer_enable')) {
                $table->bigInteger('transfer_enable')
                    ->default(null)
                    ->nullable()
                    ->after('rate')
                    ->comment('Traffic limit , 0 or null=no limit');
            }
            if (!Schema::hasColumn('v2_server', 'u')) {
                $table->bigInteger('u')
                    ->default(0)
                    ->after('transfer_enable')
                    ->comment('upload traffic');
            }
            if (!Schema::hasColumn('v2_server', 'd')) {
                $table->bigInteger('d')
                    ->default(0)
                    ->after('u')
                    ->comment('donwload traffic');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_server', function (Blueprint $table) {
            $table->dropColumn(['transfer_enable', 'u', 'd']);
        });
    }
};
