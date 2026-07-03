<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TrafficPackageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class TrafficFetchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $data;
    protected $server;
    protected $protocol;
    protected $timestamp;
    public $tries = 1;
    public $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $server, array $data, $protocol, int $timestamp)
    {
        $this->onQueue('traffic_fetch');
        $this->server = $server;
        $this->data = $data;
        $this->protocol = $protocol;
        $this->timestamp = $timestamp;
    }

    public function handle(): void
    {
        $trafficPackageService = app(TrafficPackageService::class);

        foreach ($this->data as $uid => $v) {
            DB::transaction(function () use ($trafficPackageService, $uid, $v) {
                $uploadBytes = (int) ($v[0] * $this->server['rate']);
                $downloadBytes = (int) ($v[1] * $this->server['rate']);
                $consumption = $trafficPackageService->consume((int) $uid, $uploadBytes, $downloadBytes);

                if ($consumption['plan_upload'] > 0 || $consumption['plan_download'] > 0) {
                    User::where('id', $uid)
                        ->incrementEach(
                            [
                                'u' => $consumption['plan_upload'],
                                'd' => $consumption['plan_download'],
                            ],
                            ['t' => time()]
                        );
                    return;
                }

                User::where('id', $uid)->update(['t' => time()]);
            }, 3);
        }
    }
}
