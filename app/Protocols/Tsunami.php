<?php

namespace App\Protocols;

use App\Models\Server;
use App\Support\AbstractProtocol;

class Tsunami extends AbstractProtocol
{
    public $flags = ['tsunami'];

    public $allowedProtocols = [Server::TYPE_TSUNAMI];

    public function handle()
    {
        $lines = '';
        foreach ($this->servers as $server) {
            $settings = $server['protocol_settings'] ?? [];
            $transport = data_get($settings, 'transport', []);
            $fronting = data_get($settings, 'fronting', []);
            $payload = [
                'v' => 1,
                'name' => $server['name'],
                'server' => $server['host'],
                'port' => (int) $server['port'],
                'token' => $server['password'],
                'tls' => [
                    'sni' => data_get($settings, 'tls.server_name') ?: $server['host'],
                    'insecure' => (bool) data_get($settings, 'tls.allow_insecure', false),
                ],
                'transport' => [
                    'type' => data_get($transport, 'mode', 'raw'),
                    'path' => data_get($transport, 'path', '/assets/update'),
                    'host' => data_get($transport, 'host') ?: $server['host'],
                ],
            ];
            if (data_get($fronting, 'enabled', false)) {
                $payload['transport']['fronting_key'] = data_get($fronting, 'secret');
            }

            $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
            $lines .= "tsunami://{$encoded}\r\n";
        }

        return response(base64_encode($lines))
            ->header('content-type', 'text/plain')
            ->header('subscription-userinfo', "upload={$this->user['u']}; download={$this->user['d']}; total={$this->user['transfer_enable']}; expire={$this->user['expired_at']}");
    }
}
