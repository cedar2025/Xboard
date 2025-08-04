<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_plugins', function (Blueprint $table) {
            $table->string('type', 20)->default('feature')->after('code')->comment('插件类型：feature功能性，payment支付型');
            $table->index(['type', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('v2_plugins', function (Blueprint $table) {
            $table->dropIndex(['type', 'is_enabled']);
            $table->dropColumn('type');
        });
    }
}; 