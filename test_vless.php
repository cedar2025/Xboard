<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$server = [
    'host' => 'example.com',
    'port' => 443,
    'name' => 'test',
    'protocol_settings' => [
        'network' => 'xhttp',
        'tls' => 1,
        'tls_settings' => [
            'server_name' => 'sni.example.com',
            'allow_insecure' => 0
        ],
        'network_settings' => [
            'path' => '/path',
            'host' => 'host.example.com',
            'fingerprint' => 'chrome',
            'mode' => 'auto',
            'extra' => ['noSSEHeader' => false]
        ],
        'flow' => ''
    ]
];

echo "Testing buildVless:\n";
echo App\Protocols\General::buildVless('uuid', $server);
