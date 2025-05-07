<?php

namespace App\Console\Commands;

use App\Services\StatisticalService;
use Illuminate\Console\Command;
use App\Models\Stat;
use Illuminate\Support\Facades\Log;

class XboardStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xboard:statistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计任务';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $startAt = microtime(true);
        ini_set('memory_limit', -1);
        // $this->statUser();
        // $this->statServer();
        $this->stat();
        info('统计任务执行完毕。耗时:' . (microtime(true) - $startAt) / 1000);
    }


    private function stat()
    {
        try {
            $endAt = strtotime(date('Y-m-d'));
            $startAt = strtotime('-1 day', $endAt);
            $statisticalService = new StatisticalService();
            $statisticalService->setStartAt($startAt);
            $statisticalService->setEndAt($endAt);
            $data = $statisticalService->generateStatData();
            $data['record_at'] = $startAt;
            $data['record_type'] = 'd';
            $statistic = Stat::where('record_at', $startAt)
                ->where('record_type', 'd')
                ->first();
            if ($statistic) {
                $statistic->update($data);
                return;
            }
            Stat::create($data);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
        }
    }
}
