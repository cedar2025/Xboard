<?php
require __DIR__ . '/vendor/autoload.php';

$server = [
    'name' => 'Test Node',
    'host' => '1.1.1.1',
    'port' => 443,
    'protocol_settings' => [
        'network' => 'xhttp',
        'network_settings' => [
            'path' => '/zones',
            'mode' => 'stream-up',
            'host' => 'test.com',
            'extra' => '{"xmux":{"max-concurrency":"1"},"sc-min-posts-interval-ms":"5"}'
        ]
    ]
];

$extra = data_get($server['protocol_settings'], 'network_settings.extra', []);
if (is_string($extra)) {
    $extra = json_decode($extra, true) ?: [];
}

$opts = array_merge([
    'path' => data_get($server['protocol_settings'], 'network_settings.path', '/'),
    'mode' => data_get($server['protocol_settings'], 'network_settings.mode', 'auto'),
    'host' => data_get($server['protocol_settings'], 'network_settings.host', $server['host']),
], (array) $extra);

$opts = array_filter($opts, function($v) {
    return $v !== null && $v !== '';
});

$proxy = [
    'name' => $server['name'],
    'type' => 'vless',
    'network' => 'xhttp',
    'xhttp-opts' => $opts
];

use Symfony\Component\Yaml\Yaml;

echo Yaml::dump([$proxy], 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
