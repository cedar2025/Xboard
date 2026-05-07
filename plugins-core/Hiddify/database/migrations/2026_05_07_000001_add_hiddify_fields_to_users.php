<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            if (! Schema::hasColumn('v2_user', 'hiddify_user_id')) {
                $table->string('hiddify_user_id')->nullable()->after('token');
            }

            if (! Schema::hasColumn('v2_user', 'hiddify_subscribe_url')) {
                $table->text('hiddify_subscribe_url')->nullable()->after('hiddify_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            if (Schema::hasColumn('v2_user', 'hiddify_subscribe_url')) {
                $table->dropColumn('hiddify_subscribe_url');
            }
            if (Schema::hasColumn('v2_user', 'hiddify_user_id')) {
                $table->dropColumn('hiddify_user_id');
            }
        });
    }
};
