<?php

namespace App\Plugins\Telegram\Commands;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Plugins\Telegram\Telegram;

class UnBind extends Telegram {
    public $command = '/unbind';
    public $description = '将Telegram账号从网站解绑';

    public function handle($message, $match = []) {
        if (!$message->is_private) return;
        if (!isset($message->args[0])) {
            $user = User::where('telegram_id', $message->chat_id)->first();
        } else {
            $chat = User::where('telegram_id', $message->chat_id)->first();
            if (!$chat) return;
            if (!($chat->is_admin || $chat->is_staff)) return;
            if (strpos($message->args[0], '@') !== true) {
                $user = User::where('email', $message->args[0])->first();
            } else {
                $user = User::where('telegram_id', $message->args[0])->first();
            }
        }
        $user->telegram_id = NULL;
        if (!$user->save()) {
            throw new ApiException('解绑失败');
        }
        $telegramService->sendMessage($message->chat_id, '解绑成功', 'markdown');
    }
}
