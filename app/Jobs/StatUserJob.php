<?php


namespace App\Jobs;

use App\Models\StatUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StatUserJob implements ShouldQueue
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
        // Calculate record timestamp
        $recordAt = $this->recordType === 'm'
            ? strtotime(date('Y-m-01'))
            : strtotime(date('Y-m-d'));

        foreach ($this->data as $uid => $v) {
            DB::transaction(function () use ($uid, $v, $recordAt) {
                $stat = StatUser::lockForUpdate()
                    ->where('user_id', $uid)
                    ->where('server_rate', $this->server['rate'])
                    ->where('record_at', $recordAt)
                    ->where('record_type', $this->recordType)
                    ->first();
                if ($stat) {
                    $stat->u += ($v[0] * $this->server['rate']);
                    $stat->d += ($v[1] * $this->server['rate']);
                    $stat->save();
                } else {
                    StatUser::create([
                        'user_id' => $uid,
                        'server_rate' => $this->server['rate'],
                        'record_at' => $recordAt,
                        'record_type' => $this->recordType,
                        'u' => ($v[0] * $this->server['rate']),
                        'd' => ($v[1] * $this->server['rate']),
                    ]);
                }
            });
        }
    }
}