<?php

namespace App\Jobs;

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
    public $timeout = 10;

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
        // 获取子节点
        $targetServer = $this->childServer ?? $this->server;
        foreach ($this->data as $uid => $v) {
            $u = $v[0];
            $d = $v[1];
            $result = \DB::transaction(function () use ($uid, $u, $d, $targetServer) {
                $user = \DB::table('v2_user')->lockForUpdate()->where('id', $uid)->first();
                if (!$user) {
                    return true;
                }
                $newTime = time();
                $newU = $user->u + ($u * $targetServer['rate']);
                $newD = $user->d + ($d * $targetServer['rate']);
                $rows = \DB::table('v2_user')
                    ->where('id', $uid)
                    ->update([
                        't' => $newTime,
                        'u' => $newU,
                        'd' => $newD,
                    ]);
                if ($rows === 0) {
                    return false;
                }
                return true;
            }, 3);
            if (!$result) {
                TrafficFetchJob::dispatch($u, $d, $uid, $targetServer, $this->protocol);
            }
        }
    }
}
