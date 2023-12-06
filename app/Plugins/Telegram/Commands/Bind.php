<?php

namespace App\Plugins\Telegram\Commands;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Plugins\Telegram\Telegram;

class Bind extends Telegram {
    public $command = '/bind';
    public $description = '将Telegram账号绑定到网站';

    public function handle($message, $match = []) {
        if (!$message->is_private) return;
        if (!isset($message->args[0])) {
            throw new ApiException('参数有误，请携带订阅地址发送', 422);
        }
        $subscribeUrl = $message->args[0];
        $subscribeUrl = parse_url($subscribeUrl);
        parse_str($subscribeUrl['query'], $query);
        $token = $query['token'];
        if (!$token) {
            throw new ApiException('订阅地址无效');
        }
        $user = User::where('token', $token)->first();
        if (!$user) {
            throw new ApiException('用户不存在');
        }
        if ($user->telegram_id) {
            throw new ApiException('该账号已经绑定了Telegram账号');
        }
        $user->telegram_id = $message->chat_id;
        if (!$user->save()) {
            throw new ApiException('设置失败');
        }
        $telegramService = $this->telegramService;
        $telegramService->sendMessage($message->chat_id, '绑定成功');
    }
}
