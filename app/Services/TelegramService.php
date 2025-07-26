<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Jobs\SendTelegramJob;
use App\Models\User;
use App\Services\Plugin\HookManager;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected PendingRequest $http;
    protected string $apiUrl;

    public function __construct(?string $token = null)
    {
        $botToken = admin_setting('telegram_bot_token', $token);
        $this->apiUrl = "https://api.telegram.org/bot{$botToken}/";

        $this->http = Http::timeout(30)
            ->retry(3, 1000)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);
    }

    public function sendMessage(int $chatId, string $text, string $parseMode = ''): void
    {
        $text = $parseMode === 'markdown' ? str_replace('_', '\_', $text) : $text;

        $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode ?: null,
        ]);
    }

    public function approveChatJoinRequest(int $chatId, int $userId): void
    {
        $this->request('approveChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    public function declineChatJoinRequest(int $chatId, int $userId): void
    {
        $this->request('declineChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    public function getMe(): object
    {
        return $this->request('getMe');
    }

    public function setWebhook(string $url): object
    {
        $result = $this->request('setWebhook', ['url' => $url]);
        return $result;
    }

    /**
     * 注册 Bot 命令列表
     */
    public function registerBotCommands(): void
    {
        try {
            $commands = HookManager::filter('telegram.bot.commands', []);

            if (empty($commands)) {
                Log::warning('没有找到任何 Telegram Bot 命令');
                return;
            }

            $this->request('setMyCommands', [
                'commands' => json_encode($commands),
                'scope' => json_encode(['type' => 'default'])
            ]);

            Log::info('Telegram Bot 命令注册成功', [
                'commands_count' => count($commands),
                'commands' => $commands
            ]);

        } catch (\Exception $e) {
            Log::error('Telegram Bot 命令注册失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 获取当前注册的命令列表
     */
    public function getMyCommands(): object
    {
        return $this->request('getMyCommands');
    }

    /**
     * 删除所有命令
     */
    public function deleteMyCommands(): object
    {
        return $this->request('deleteMyCommands');
    }

    public function sendMessageWithAdmin(string $message, bool $isStaff = false): void
    {
        $query = User::where('telegram_id', '!=', null);
        $query->where(
            fn($q) => $q->where('is_admin', 1)
                ->when($isStaff, fn($q) => $q->orWhere('is_staff', 1))
        );
        $users = $query->get();
        foreach ($users as $user) {
            SendTelegramJob::dispatch($user->telegram_id, $message);
        }
    }

    protected function request(string $method, array $params = []): object
    {
        try {
            $response = $this->http->get($this->apiUrl . $method, $params);

            if (!$response->successful()) {
                throw new ApiException("HTTP 请求失败: {$response->status()}");
            }

            $data = $response->object();

            if (!isset($data->ok)) {
                throw new ApiException('无效的 Telegram API 响应');
            }

            if (!$data->ok) {
                $description = $data->description ?? '未知错误';
                throw new ApiException("Telegram API 错误: {$description}");
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Telegram API 请求失败', [
                'method' => $method,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            throw new ApiException("Telegram 服务错误: {$e->getMessage()}");
        }
    }
}
