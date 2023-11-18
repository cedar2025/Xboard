<?php

return [

    'google_cloud' => [
        'key_file' => env('GOOGLE_CLOUD_KEY_FILE') ? base_path(env('GOOGLE_CLOUD_KEY_FILE')) : null,
        'storage_bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
    ],

];