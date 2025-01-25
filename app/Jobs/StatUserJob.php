<?php


namespace App\Jobs;

use App\Models\StatUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatUserJob implements ShouldQueue
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
    public function __construct(array $server, array $data, string $protocol, string $recordType = 'd')
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

        foreach ($this->data as $uid => $v) {
            try {
                DB::transaction(function () use ($uid, $v, $recordAt) {
                    $affected = StatUser::where([
                        'user_id' => $uid,
                        'server_rate' => $this->server['rate'],
                        'record_at' => $recordAt,
                        'record_type' => $this->recordType,
                    ])->update([
                        'u' => DB::raw('u + ' . ($v[0] * $this->server['rate'])),
                        'd' => DB::raw('d + ' . ($v[1] * $this->server['rate'])),
                    ]);

                    if (!$affected) {
                        StatUser::create([
                            'user_id' => $uid,
                            'server_rate' => $this->server['rate'],
                            'record_at' => $recordAt,
                            'record_type' => $this->recordType,
                            'u' => ($v[0] * $this->server['rate']),
                            'd' => ($v[1] * $this->server['rate']),
                        ]);
                    }
                }, 3);
            } catch (\Exception $e) {
                Log::error('StatUserJob failed for user ' . $uid . ': ' . $e->getMessage());
                throw $e;
            }
        }
    }
}