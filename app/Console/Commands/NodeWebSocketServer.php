<?php

namespace App\Console\Commands;

use App\WebSocket\NodeWorker;
use Illuminate\Console\Command;

class NodeWebSocketServer extends Command
{
    protected $signature = 'ws-server
        {action=start : start | stop | restart | reload | status}
        {--d : Start in daemon mode}
        {--host=0.0.0.0 : Listen address}
        {--port=8076 : Listen port}';

    protected $description = 'Start the WebSocket server for node-panel synchronization';

    public function handle(): void
    {
        global $argv;
        $action = $this->argument('action');

        $argv[1] = $action;
        if ($this->option('d')) {
            $argv[2] = '-d';
        }

        $host = $this->option('host');
        $port = $this->option('port');

        $worker = new NodeWorker($host, $port);
        $worker->run();
    }
}
