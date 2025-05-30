<?php

namespace App\Console\Commands;

use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRemindMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:remindMail 
                            {--chunk-size=500 : 每批处理的用户数量}
                            {--force : 强制执行，跳过确认}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发送提醒邮件';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (!admin_setting('remind_mail_enable', false)) {
            $this->warn('邮件提醒功能未启用');
            return 0;
        }

        $chunkSize = max(100, min(2000, (int) $this->option('chunk-size')));
        $mailService = new MailService();

        $totalUsers = $mailService->getTotalUsersNeedRemind();
        if ($totalUsers === 0) {
            $this->info('没有需要发送提醒邮件的用户');
            return 0;
        }

        $this->displayInfo($totalUsers, $chunkSize);

        if (!$this->option('force') && !$this->confirm("确定要发送提醒邮件给 {$totalUsers} 个用户吗？")) {
            return 0;
        }

        $startTime = microtime(true);
        $progressBar = $this->output->createProgressBar(ceil($totalUsers / $chunkSize));
        $progressBar->start();

        $statistics = $mailService->processUsersInChunks($chunkSize, function () use ($progressBar) {
            $progressBar->advance();
        });

        $progressBar->finish();
        $this->newLine();

        $this->displayResults($statistics, microtime(true) - $startTime);
        $this->logResults($statistics);

        return 0;
    }

    private function displayInfo(int $totalUsers, int $chunkSize): void
    {
        $this->table(['项目', '值'], [
            ['需要处理的用户', number_format($totalUsers)],
            ['批次大小', $chunkSize],
            ['预计批次', ceil($totalUsers / $chunkSize)],
        ]);
    }

    private function displayResults(array $stats, float $duration): void
    {
        $this->info('✅ 提醒邮件发送完成！');

        $this->table(['统计项', '数量'], [
            ['总处理用户', number_format($stats['processed_users'])],
            ['过期提醒邮件', number_format($stats['expire_emails'])],
            ['流量提醒邮件', number_format($stats['traffic_emails'])],
            ['跳过用户', number_format($stats['skipped'])],
            ['错误数量', number_format($stats['errors'])],
            ['总耗时', round($duration, 2) . ' 秒'],
            ['平均速度', round($stats['processed_users'] / max($duration, 0.1), 1) . ' 用户/秒'],
        ]);

        if ($stats['errors'] > 0) {
            $this->warn("⚠️  有 {$stats['errors']} 个用户的邮件发送失败，请检查日志");
        }
    }

    private function logResults(array $statistics): void
    {
        Log::info('SendRemindMail命令执行完成', ['statistics' => $statistics]);
    }
}
