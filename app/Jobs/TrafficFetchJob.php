<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MailService;
use App\Services\ServerService;
use App\Services\StatisticalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrafficFetchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $data;
    protected $server;
    protected $childServer;
    protected $protocol;
    protected $timestamp;
    public $tries = 3;
    public $timeout = 10;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $server, array $data, $protocol, int $timestamp, $nodeIp = null)
    {
        $this->onQueue('traffic_fetch');
        $this->server = $server;
        $this->data = $data;
        $this->protocol = $protocol;
        $this->timestamp = $timestamp;
        // 获取子节点
        $serverService = new ServerService();
        $this->childServer = ($this->server['parent_id'] == null && !blank($nodeIp)) ?  $serverService->getChildServer($this->server['id'], $this->protocol, $nodeIp) : null;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        \DB::transaction(function () {
            if ($this->attempts() === 1){
                $statService = new StatisticalService();
                $statService->setStartAt($this->timestamp);
                $statService->setUserStats();
                $statService->setServerStats();
            }
            
            // 获取子节点\
            
            $targetServer = $this->childServer ?? $this->server;
            foreach ($this->data as $uid => $v) {
                $u = $v[0];
                $d = $v[1];
                $user = \DB::table('v2_user')->lockForUpdate()->where('id', $uid)->first();
                if (!$user) {
                    continue;
                }
                if ($this->attempts() === 1){ // 写缓存
                    $statService->statUser($targetServer['rate'], $uid, $u, $d); //如果存在子节点则使用子节点的倍率
                    if(!blank($this->childServer)){ //如果存在子节点，则给子节点计算流量
                        $statService->statServer($this->childServer['id'], $this->protocol, $u, $d);
                    }
                    $statService->statServer($this->server['id'], $this->protocol, $u, $d);
                }
                $newTime = time();
                $newU = $user->u + ($v[0] * $targetServer['rate']);
                $newD = $user->d + ($v[1] * $targetServer['rate']);
                \DB::table('v2_user')
                    ->where('id', $uid)
                    ->update([
                        't' => $newTime,
                        'u' => $newU,
                        'd' => $newD,
                    ]);
            }
        }, 3);
    }
}
