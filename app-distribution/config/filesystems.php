<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
        'artifacts' => [
            'driver' => 'local',
            'root' => storage_path('app/artifacts'),
            'throw' => false,
        ],
    ],
];
