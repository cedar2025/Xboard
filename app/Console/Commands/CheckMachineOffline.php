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
    protected $description = 'Alert admin when a machine heartbeats out (3 push intervals) or sustains high load';

    private const ALERT_STATE_KEY = 'machine_offline_alert_state';

    // 高负载判定阈值（可被 admin_setting 覆盖）；连续 HIGH_LOAD_ROUNDS 轮超限才告警（1 分钟/轮）
    private const HIGH_LOAD_ROUNDS = 5;

    private function checkHighLoad(ServerMachine $machine, array $alertState, array &$newState): void
    {
        $cpuLimit = (float) (admin_setting('machine_alert_cpu_threshold', 90));
        $memLimit = (float) (admin_setting('machine_alert_mem_threshold', 90));
        $load = $machine->load_status ?? [];
        $cpu = (float) ($load['cpu'] ?? 0);
        $memUsed = (int) ($load['mem']['used'] ?? 0);
        $memTotal = (int) ($load['mem']['total'] ?? 0);
        $memPct = $memTotal > 0 ? $memUsed / $memTotal * 100 : 0;
        $high = $cpu >= $cpuLimit || $memPct >= $memLimit;

        $roundsKey = "machine_highload_rounds_{$machine->id}";
        $alertedKey = "machine_highload_alerted_{$machine->id}";
        $rounds = (int) Cache::get($roundsKey, 0);

        if (!$high) {
            Cache::forget($roundsKey);
            if (Cache::get($alertedKey, false)) {
                Cache::put($alertedKey, false, 86400);
                $this->notify("🟢 负载恢复: {$machine->name} (#{$machine->id})");
            }
            return;
        }

        $rounds++;
        if ($rounds >= self::HIGH_LOAD_ROUNDS && !Cache::get($alertedKey, false)) {
            Cache::put($alertedKey, true, 86400);
            $this->notify(
                "🟠 机器高负载: {$machine->name} (#{$machine->id})\n"
                . sprintf("CPU %.1f%% / 内存 %.1f%%（阈值 %.0f%%/%.0f%%，持续 %d 分钟）", $cpu, $memPct, $cpuLimit, $memLimit, $rounds)
                . "\nagent: " . ($machine->agent_version ?? 'unknown')
            );
            Cache::put($roundsKey, $rounds, 3600);
            return;
        }
        Cache::put($roundsKey, $rounds, 3600);
    }

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

            if (!$offline) {
                $this->checkHighLoad($machine, $alertState, $newState);
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
