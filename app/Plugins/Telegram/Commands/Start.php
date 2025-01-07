<?php

namespace App\Plugins\Telegram\Commands;

use App\Plugins\Telegram\Telegram;

class Start extends Telegram {
    public $command = '/start';
    public $description = 'telegram机器人初始化';

    public function handle($message, $match = []) {
        if (!$message->is_private) return;
        $telegramService = $this->telegramService;
        $text = "/start 显示所有可用指令\n /bind+空格+订阅链接，将telegram绑定至账户\n /traffic 获取当前使用流量 \n /getlatesturl 获取网站最新网址 \n /unbind 解绑telegram账户";
        $telegramService->sendMessage($message->chat_id, $text, 'markdown');
    }
}
