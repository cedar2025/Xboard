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
            // Change last_login_ip from integer to bigint unsigned to handle larger IP values
            $table->bigInteger('last_login_ip')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            // Revert back to integer (this may cause data loss if there are large values)
            $table->integer('last_login_ip')->nullable()->change();
        });
    }
}; 