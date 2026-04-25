<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Backfill default utls for legacy vless reality nodes after the uTLS refactor.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_server')) {
            return;
        }

        DB::table('v2_server')
            ->where('type', 'vless')
            ->orderBy('id')
            ->chunkById(200, function ($servers) {
                foreach ($servers as $server) {
                    $settings = json_decode($server->protocol_settings ?? '', true);
                    if (!is_array($settings) || (int) ($settings['tls'] ?? 0) != 2) {
                        continue;
                    }

                    $existing = $settings['utls'] ?? null;
                    if (is_array($existing) && ($existing['enabled'] ?? false) === true) {
                        continue;
                    }

                    $settings['utls'] = [
                        'enabled' => true,
                        'fingerprint' => is_array($existing) && !empty($existing['fingerprint'])
                            ? $existing['fingerprint']
                            : 'chrome',
                    ];

                    DB::table('v2_server')
                        ->where('id', $server->id)
                        ->update(['protocol_settings' => json_encode($settings)]);
                }
            });
    }

    public function down(): void
    {
    }
};
