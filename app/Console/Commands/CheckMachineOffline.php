<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailJob;
use App\Models\ServerMachine;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckMachineOffline extends Command
{
    protected $signature = 'check:machine-offline';
    protected $description = 'Alert admin when a machine heartbeats out (3 push intervals)';

    private const ALERT_STATE_KEY = 'machine_offline_alert_state';

    public function handle(): int
    {
        $interval = max(180, (int) admin_setting('server_push_interval', 60) * 3);
        $threshold = now()->subSeconds($interval)->timestamp;

        $machines = ServerMachine::where('is_active', 1)->get();
        $alertState = Cache::get(self::ALERT_STATE_KEY, []);
        $newState = [];

        foreach ($machines as $machine) {
            $offline = $machine->last_seen_at === null || $machine->last_seen_at < $threshold;
            $wasOffline = ($alertState[$machine->id] ?? false);
            $newState[$machine->id] = $offline;

            // 只在状态翻转时告警：上线→下线发告警，下线→上线发恢复
            if ($offline && !$wasOffline) {
                $this->notify(
                    "🔴 机器离线: {$machine->name} (#{$machine->id})\n"
                    . "最后心跳: " . ($machine->last_seen_at ? date('Y-m-d H:i:s', $machine->last_seen_at) : '从未')
                    . "\nagent: " . ($machine->agent_version ?? 'unknown')
                );
            } elseif (!$offline && $wasOffline) {
                $this->notify("🟢 机器恢复: {$machine->name} (#{$machine->id})");
            }
        }

        // 清理已删除机器的状态
        Cache::put(self::ALERT_STATE_KEY, array_intersect_key($newState, $machines->keyBy('id')->toArray()), 86400);
        return self::SUCCESS;
    }

    private function notify(string $message): void
    {
        $this->info($message);

        $chatId = (string) admin_setting('machine_alert_telegram_chat_id', '');
        if ($chatId !== '') {
            try {
                app(TelegramService::class)->sendMessage((int) $chatId, $message);
                return;
            } catch (\Throwable $e) {
                Log::warning('machine offline alert via telegram failed: ' . $e->getMessage());
            }
        }

        $email = admin_setting('machine_alert_email', admin_setting('smtp_username', ''));
        if ($email) {
            SendEmailJob::dispatch([
                'email' => $email,
                'subject' => '[Xboard] 机器状态告警',
                'template_name' => 'generic',
                'template_value' => ['content' => $message],
            ]);
        }
    }
}
