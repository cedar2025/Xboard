<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('v2_server_machine_load_history', function (Blueprint $table) {
            $table->double('net_in_speed')->nullable()->after('disk_used');
            $table->double('net_out_speed')->nullable()->after('net_in_speed');
        });
    }

    public function down(): void
    {
        Schema::table('v2_server_machine_load_history', function (Blueprint $table) {
            $table->dropColumn(['net_in_speed', 'net_out_speed']);
        });
    }
};
