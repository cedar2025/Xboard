<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class ExportV2Log extends Command
{
    protected $signature = 'log:export {days=1 : The number of days to export logs for}';
    protected $description = 'Export v2_log table records of the specified number of days to a file';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $days = $this->argument('days');
        $date = Carbon::now()->subDays($days)->startOfDay();

        $logs = \DB::table('v2_log')
                    ->where('created_at', '>=', $date->timestamp)
                    ->get();

        $fileName = "v2_logs_" . Carbon::now()->format('Y_m_d_His') . ".csv";
        $handle = fopen(storage_path("logs/$fileName"), 'w');

        // 根据您的表结构
        fputcsv($handle, ['Level', 'ID', 'Title', 'Host', 'URI', 'Method', 'Data', 'IP', 'Context', 'Created At', 'Updated At']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->level,
                $log->id,
                $log->title, 
                $log->host, 
                $log->uri, 
                $log->method, 
                $log->data, 
                $log->ip, 
                $log->context, 
                Carbon::createFromTimestamp($log->created_at)->toDateTimeString(), 
                Carbon::createFromTimestamp($log->updated_at)->toDateTimeString()
            ]);
        }

        fclose($handle);
        $this->info("日志成功导出到：  ". storage_path("logs/$fileName"));
    }
}
