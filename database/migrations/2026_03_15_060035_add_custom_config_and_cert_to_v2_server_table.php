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
            $table->json('custom_outbounds')->nullable()->after('protocol_settings');
            $table->json('custom_routes')->nullable()->after('custom_outbounds');
            $table->json('cert_config')->nullable()->after('custom_routes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_server', function (Blueprint $table) {
            $table->dropColumn(['custom_outbounds', 'custom_routes', 'cert_config']);
        });
    }
};
