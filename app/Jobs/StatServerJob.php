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

    public function handle(): void
    {
        $recordAt = $this->recordType === 'm'
            ? strtotime(date('Y-m-01'))
            : strtotime(date('Y-m-d'));

        $u = $d = 0;
        foreach ($this->data as $traffic) {
            $u += $traffic[0];
            $d += $traffic[1];
        }

        try {
            $this->processServerStat($u, $d, $recordAt);
        } catch (\Exception $e) {
            Log::error('StatServerJob failed for server ' . $this->server['id'] . ': ' . $e->getMessage());
            throw $e;
        }
    }

    protected function processServerStat(int $u, int $d, int $recordAt): void
    {
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            $this->processServerStatForSqlite($u, $d, $recordAt);
        } elseif ($driver === 'pgsql') {
            $this->processServerStatForPostgres($u, $d, $recordAt);
        } else {
            $this->processServerStatForOtherDatabases($u, $d, $recordAt);
        }
    }

    protected function processServerStatForSqlite(int $u, int $d, int $recordAt): void
    {
        DB::transaction(function () use ($u, $d, $recordAt) {
            $existingRecord = StatServer::where([
                'record_at' => $recordAt,
                'server_id' => $this->server['id'],
                'server_type' => $this->protocol,
                'record_type' => $this->recordType,
            ])->first();

            if ($existingRecord) {
                $existingRecord->update([
                    'u' => $existingRecord->u + $u,
                    'd' => $existingRecord->d + $d,
                    'updated_at' => time(),
                ]);
            } else {
                StatServer::create([
                    'record_at' => $recordAt,
                    'server_id' => $this->server['id'],
                    'server_type' => $this->protocol,
                    'record_type' => $this->recordType,
                    'u' => $u,
                    'd' => $d,
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
            }
        }, 3);
    }

    protected function processServerStatForOtherDatabases(int $u, int $d, int $recordAt): void
    {
        StatServer::upsert(
            [
                'record_at' => $recordAt,
                'server_id' => $this->server['id'],
                'server_type' => $this->protocol,
                'record_type' => $this->recordType,
                'u' => $u,
                'd' => $d,
                'created_at' => time(),
                'updated_at' => time(),
            ],
            ['server_id', 'server_type', 'record_at', 'record_type'],
            [
                'u' => DB::raw("u + VALUES(u)"),
                'd' => DB::raw("d + VALUES(d)"),
                'updated_at' => time(),
            ]
        );
    }

    /**
     * PostgreSQL upsert with arithmetic increments using ON CONFLICT ... DO UPDATE
     */
    protected function processServerStatForPostgres(int $u, int $d, int $recordAt): void
    {
        $table = (new StatServer())->getTable();
        $now = time();

        // Use parameter binding to avoid SQL injection and keep maintainability
        $sql = "INSERT INTO {$table} (record_at, server_id, server_type, record_type, u, d, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (server_id, server_type, record_at)
                DO UPDATE SET
                    u = {$table}.u + EXCLUDED.u,
                    d = {$table}.d + EXCLUDED.d,
                    updated_at = EXCLUDED.updated_at";

        DB::statement($sql, [
            $recordAt,
            $this->server['id'],
            $this->protocol,
            $this->recordType,
            $u,
            $d,
            $now,
            $now,
        ]);
    }
}
