<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * FlClash-compatible subscription encryption: key = MD5(login password).
     */
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->char('subscription_encryption_key', 32)->nullable()->after('password_salt')
                ->comment('MD5(plain password) hex, for subscription body encryption');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropColumn('subscription_encryption_key');
        });
    }
};
