<?php

namespace App\Protocols;

use App\Support\AbstractProtocol;

class Shadowsocks extends AbstractProtocol
{
    public $flags = ['shadowsocks'];

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;

        $configs = [];
        $subs = [];
        $subs['servers'] = [];
        $subs['bytes_used'] = '';
        $subs['bytes_remaining'] = '';

        $bytesUsed = $user['u'] + $user['d'];
        $bytesRemaining = $user['transfer_enable'] - $bytesUsed;

        foreach ($servers as $item) {
            if (
                $item['type'] === 'shadowsocks'
                && in_array(data_get($item, 'protocol_settings.cipher'), ['aes-128-gcm', 'aes-256-gcm', 'aes-192-gcm', 'chacha20-ietf-poly1305'])
            ) {
                array_push($configs, self::SIP008($item, $user));
            }
        }

        $subs['version'] = 1;
        $subs['bytes_used'] = $bytesUsed;
        $subs['bytes_remaining'] = $bytesRemaining;
        $subs['servers'] = array_merge($subs['servers'], $configs);

        return response()->json($subs)
            ->header('content-type', 'application/json');
    }

    public static function SIP008($server, $user)
    {
        $config = [
            "id" => $server['id'],
            "remarks" => $server['name'],
            "server" => $server['host'],
            "server_port" => $server['port'],
            "password" => $server['password'],
            "method" => data_get($server, 'protocol_settings.cipher')
        ];
        return $config;
    }
}
