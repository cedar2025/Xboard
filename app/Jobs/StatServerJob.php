<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StatServer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StatServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;
    protected array $server;
    protected string $protocol;
    protected string $recordType;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(array $server, array $data, $protocol, string $recordType = 'd')
    {
        $this->onQueue('stat');
        $this->data = $data;
        $this->server = $server;
        $this->protocol = $protocol;
        $this->recordType = $recordType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Calculate record timestamp
        $recordAt = $this->recordType === 'm'
            ? strtotime(date('Y-m-01'))
            : strtotime(date('Y-m-d'));

        // Aggregate traffic data
        $u = $d = 0;
        foreach ($this->data as $traffic) {
            $u += $traffic[0];
            $d += $traffic[1];
        }

        // Update or create traffic stats
        DB::transaction(function () use ($u, $d, $recordAt) {
            StatServer::updateOrCreate(
                [
                    'record_at' => $recordAt,
                    'server_id' => $this->server['id'],
                    'server_type' => $this->protocol,
                    'record_type' => $this->recordType,
                ],
                [
                    'u' => DB::raw("COALESCE(u, 0) + $u"),
                    'd' => DB::raw("COALESCE(d, 0) + $d"),
                ]
            );
        });
    }
}
