<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('v2_server')
            ->where('type', 'trojan')
            ->chunkById(100, function ($servers) {
                foreach ($servers as $server) {
                    $settings = json_decode($server->protocol_settings, true);
                    if (!$settings) continue;

                    $rootSni = $settings['server_name'] ?? null;
                    $rootInsecure = $settings['allow_insecure'] ?? false;
                    $tlsSettings = $settings['tls_settings'] ?? null;

                    $needsUpdate = false;

                    if (!is_array($tlsSettings)) {
                        if ($rootSni !== null || $rootInsecure) {
                            $settings['tls_settings'] = [
                                'server_name' => $rootSni,
                                'allow_insecure' => (bool) $rootInsecure,
                            ];
                            $needsUpdate = true;
                        }
                    } else {
                        $tlsSni = $tlsSettings['server_name'] ?? null;
                        if (($tlsSni === null || $tlsSni === '') && $rootSni !== null && $rootSni !== '') {
                            $settings['tls_settings']['server_name'] = $rootSni;
                            $needsUpdate = true;
                        }
                        if (($tlsSettings['allow_insecure'] ?? null) === null && $rootInsecure) {
                            $settings['tls_settings']['allow_insecure'] = true;
                            $needsUpdate = true;
                        }
                    }

                    if ($needsUpdate) {
                        DB::table('v2_server')
                            ->where('id', $server->id)
                            ->update(['protocol_settings' => json_encode($settings)]);
                    }
                }
            });
    }

    public function down(): void
    {
    }
};
