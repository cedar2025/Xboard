<?php

require_once __DIR__ . '/vendor/autoload.php';

use Adapterman\Adapterman;
use Workerman\Worker;

Adapterman::init();

$http_worker = new Worker('http://127.0.0.1:7010');
$http_worker->count = getenv('WEBMAN_WORKERS') ?: max(swoole_cpu_num(), 2);
$http_worker->name = 'AdapterMan';

$http_worker->onWorkerStart = static function () {
    //init();
    require __DIR__ . '/start.php';
};

$http_worker->onMessage = static function ($connection, $request) {

    static $request_count;

    $connection->send(run());
    if (++$request_count > 10000) {
        Worker::stopAll();
    }
};

Worker::runAll();
