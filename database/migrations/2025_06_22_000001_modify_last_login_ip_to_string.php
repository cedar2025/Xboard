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
        // First, drop the existing column and create a new string column
        Schema::table('v2_user', function (Blueprint $table) {
            // Drop the existing column
            $table->dropColumn('last_login_ip');
        });

        Schema::table('v2_user', function (Blueprint $table) {
            // Create a new varchar column to store both IPv4 and IPv6 addresses
            // Maximum length of IPv6 address is 45 characters
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to integer column
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropColumn('last_login_ip');
        });

        Schema::table('v2_user', function (Blueprint $table) {
            $table->integer('last_login_ip')->nullable()->after('last_login_at');
        });
    }
}; 