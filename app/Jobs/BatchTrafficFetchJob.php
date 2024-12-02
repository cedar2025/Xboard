<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BatchTrafficFetchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $data;
    protected $server;
    protected $childServer;
    protected $protocol;
    protected $timestamp;
    public $tries = 1;
    public $timeout = 20;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $server, array $data, $protocol, int $timestamp, $childServer = null)
    {
        $this->onQueue('batch_traffic_fetch');
        $this->server = $server;
        $this->data = $data;
        $this->protocol = $protocol;
        $this->timestamp = $timestamp;
        $this->childServer = $childServer;
    }

    public function handle(): void
    {
        $targetServer = $this->childServer ?? $this->server;
        foreach ($this->data as $uid => $v) {
            User::where('id', $uid)
                ->incrementEach(
                    [
                        'u' => $v[0] * $targetServer['rate'],
                        'd' => $v[1] * $targetServer['rate'],
                    ],
                    ['t' => time()]
                );
        }
    }
}
