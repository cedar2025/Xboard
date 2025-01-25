<?php


namespace App\Jobs;

use App\Models\StatServer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;
    protected array $server;
    protected string $protocol;
    protected string $recordType;

    public $tries = 3;
    public $timeout = 60;
    public $maxExceptions = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

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
        $recordAt = $this->recordType === 'm'
            ? strtotime(date('Y-m-01'))
            : strtotime(date('Y-m-d'));

        // Aggregate traffic data
        $u = $d = 0;
        foreach ($this->data as $traffic) {
            $u += $traffic[0];
            $d += $traffic[1];
        }

        try {
            DB::transaction(function () use ($u, $d, $recordAt) {
                $affected = StatServer::where([
                    'record_at' => $recordAt,
                    'server_id' => $this->server['id'],
                    'server_type' => $this->protocol,
                    'record_type' => $this->recordType,
                ])->update([
                    'u' => DB::raw('u + ' . $u),
                    'd' => DB::raw('d + ' . $d),
                ]);

                if (!$affected) {
                    StatServer::create([
                        'record_at' => $recordAt,
                        'server_id' => $this->server['id'],
                        'server_type' => $this->protocol,
                        'record_type' => $this->recordType,
                        'u' => $u,
                        'd' => $d,
                    ]);
                }
            }, 3);
        } catch (\Exception $e) {
            Log::error('StatServerJob failed for server ' . $this->server['id'] . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
