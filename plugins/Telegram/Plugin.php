<?php

namespace Plugin\Telegram;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Plugin\AbstractPlugin;
use App\Services\Plugin\HookManager;
use App\Services\TelegramService;
use App\Services\TicketService;
use App\Utils\Helper;
use Illuminate\Support\Facades\Log;

class Plugin extends AbstractPlugin
{
  private const TICKET_HISTORY_PAGE_SIZE = 5;

  protected array $commands = [];
  protected TelegramService $telegramService;

  protected array $commandConfigs = [
    '/start' => ['description' => '开始使用', 'handler' => 'handleStartCommand'],
    '/bind' => ['description' => '绑定账号', 'handler' => 'handleBindCommand'],
    '/traffic' => ['description' => '查看流量', 'handler' => 'handleTrafficCommand'],
    '/getlatesturl' => ['description' => '获取订阅链接', 'handler' => 'handleGetLatestUrlCommand'],
    '/unbind' => ['description' => '解绑账号', 'handler' => 'handleUnbindCommand'],
    '/ticket' => ['description' => '查看工单记录', 'handler' => 'handleTicketCommand'],
  ];

  public function boot(): void
  {
    $this->telegramService = new TelegramService();
    $this->registerDefaultCommands();

    $this->filter('telegram.message.handle', [$this, 'handleMessage'], 10);
    $this->listen('telegram.message.unhandled', [$this, 'handleUnknownCommand'], 10);
    $this->listen('telegram.message.error', [$this, 'handleError'], 10);
    $this->filter('telegram.bot.commands', [$this, 'addBotCommands'], 10);
    $this->listen('ticket.create.after', [$this, 'sendTicketNotify'], 10);
    $this->listen('ticket.reply.user.after', [$this, 'sendTicketNotify'], 10);
    $this->listen('user.register.after', [$this, 'sendRegisterNotify'], 10);
    $this->listen('payment.notify.success', [$this, 'sendPaymentNotify'], 10);
  }

  public function sendRegisterNotify(User $user): void
  {
    if (!$this->getConfig('enable_register_notify', true)) {
      return;
    }

    $todayRegisterCount = User::where('created_at', '>=', strtotime(date('Y-m-d')))->count();
    $monthRegisterCount = User::where('created_at', '>=', strtotime(date('Y-m-01')))->count();

    $message = sprintf(
      "🎉 *新用户注册*\n" .
      "━━━━━━━━━━━━━━━━━━━━\n" .
      "📧 邮箱: `%s`\n" .
      "⏰ 时间: `%s`\n" .
      "📈 当日/当月新增用户量: `%d / %d`",
      $user->email,
      date('Y-m-d H:i:s'),
      $todayRegisterCount,
      $monthRegisterCount
    );

    $this->sendAdminNotification($message);
  }

  public function sendPaymentNotify(Order $order): void
  {
    if (!$this->getConfig('enable_payment_notify', true)) {
      return;
    }

    $order->loadMissing(['payment', 'user']);

    $payment = $order->payment;
    if (!$payment) {
      Log::warning('支付通知失败：订单关联的支付方式不存在', ['order_id' => $order->id]);
      return;
    }

    $user = $order->user;
    if (!$user) {
      Log::warning('支付通知失败：订单关联的用户不存在', ['order_id' => $order->id]);
      return;
    }

    $todayStartAt = strtotime(date('Y-m-d'));
    $tomorrowStartAt = strtotime('+1 day', $todayStartAt);
    $todayPaidTotal = Order::where('paid_at', '>=', $todayStartAt)
      ->where('paid_at', '<', $tomorrowStartAt)
      ->whereNotIn('status', [Order::STATUS_PENDING, Order::STATUS_CANCELLED])
      ->sum('total_amount');
    $paymentCount = $this->getUserValidPaymentCount($order);
    $paymentCountLabel = $paymentCount === 1 ? '首次' : "第{$paymentCount}次";

    $message = sprintf(
      "💰 *支付成功*\n" .
      "━━━━━━━━━━━━━━━━━━━━\n" .
      "📧 邮箱: `%s`\n" .
      "💵 本次支付金额: `%s元（%s）`\n" .
      "📊 当日总收入: `%s元`\n" .
      "🧾 订单: `%s`\n" .
      "🏦 支付方式: `%s`\n" .
      "🔌 支付渠道: `%s`",
      $user->email,
      $order->total_amount / 100,
      $paymentCountLabel,
      $todayPaidTotal / 100,
      $order->trade_no,
      $payment->name,
      $payment->payment
    );
    $this->sendAdminNotification($message);
  }

  private function getUserValidPaymentCount(Order $order): int
  {
    return Order::where('user_id', $order->user_id)
      ->whereNotNull('paid_at')
      ->whereNotIn('status', [Order::STATUS_PENDING, Order::STATUS_CANCELLED])
      ->count();
  }

  public function sendTicketNotify(Ticket $ticket): void
  {
    if (!$this->getConfig('enable_ticket_notify', true)) {
      return;
    }

    $message = $ticket->messages()->latest()->first();
    $user = User::find($ticket->user_id);
    if (!$user)
      return;
    $user->load('plan');
    $transfer_enable = $this->transferToGBString($user->transfer_enable);
    $remaining_traffic = $this->transferToGBString($user->transfer_enable - $user->u - $user->d);
    $u = $this->transferToGBString($user->u);
    $d = $this->transferToGBString($user->d);
    $expired_at = $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '长期有效';
    $money = $user->balance / 100;
    $affmoney = $user->commission_balance / 100;
    $plan = $user->plan;
    $ip = request()?->ip() ?? '';
    $region = $ip ? (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? (new \Ip2Region())->simple($ip) : 'NULL') : '';
    $TGmessage = "📮 *工单提醒* #{$ticket->id}\n";
    $TGmessage .= "━━━━━━━━━━━━━━━━━━━━\n";
    $TGmessage .= "📧 邮箱: `{$user->email}`\n";
    $TGmessage .= "📍 位置: `{$region}`\n";

    if ($plan) {
      $TGmessage .= "📦 套餐: `{$plan->name}`\n";
      $TGmessage .= "📊 流量: `{$remaining_traffic}G / {$transfer_enable}G` (剩余/总计)\n";
      $TGmessage .= "⬆️⬇️ 已用: `{$u}G / {$d}G`\n";
      $TGmessage .= "⏰ 到期: `{$expired_at}`\n";
    } else {
      $TGmessage .= "📦 套餐: `未订购任何套餐`\n";
    }

    $TGmessage .= "💰 余额: `{$money}元`\n";
    $TGmessage .= "💸 佣金: `{$affmoney}元`\n";
    $TGmessage .= "━━━━━━━━━━━━━━━━━━━━\n";
    $TGmessage .= "📝 *主题*: `{$ticket->subject}`\n";
    $TGmessage .= "💬 *内容*: `{$message->message}`\n";
    $TGmessage .= "↩️ 回复本消息即可回复工单";
    $this->sendAdminNotification($TGmessage, $this->buildTicketActionKeyboard($ticket->id));
  }

  protected function registerDefaultCommands(): void
  {
    foreach ($this->commandConfigs as $command => $config) {
      $this->registerTelegramCommand($command, [$this, $config['handler']]);
    }

    $this->registerReplyHandler('/(📮.*?工单提醒.*?#?|工单ID: ?)(\\d+)/', [$this, 'handleTicketReply']);
  }

  public function registerTelegramCommand(string $command, callable $handler): void
  {
    $this->commands['commands'][$command] = $handler;
  }

  public function registerReplyHandler(string $regex, callable $handler): void
  {
    $this->commands['replies'][$regex] = $handler;
  }

  /**
   * 发送消息给用户
   */
  protected function sendMessage(object $msg, string $message): void
  {
    $this->telegramService->sendMessage($msg->chat_id, $message, 'markdown');
  }

  /**
   * 检查是否为私聊
   */
  protected function checkPrivateChat(object $msg): bool
  {
    if (!$msg->is_private) {
      $this->sendMessage($msg, '请在私聊中使用此命令');
      return false;
    }
    return true;
  }

  /**
   * 获取绑定的用户
   */
  protected function getBoundUser(object $msg): ?User
  {
    $user = User::where('telegram_id', $msg->chat_id)->first();
    if (!$user) {
      $this->sendMessage($msg, '请先绑定账号');
      return null;
    }
    return $user;
  }

  public function handleStartCommand(object $msg): void
  {
    $welcomeTitle = $this->getConfig('start_welcome_title', '🎉 欢迎使用 XBoard Telegram Bot！');
    $botDescription = $this->getConfig('start_bot_description', '🤖 我是您的专属助手，可以帮助您：\\n• 绑定您的 XBoard 账号\\n• 查看流量使用情况\\n• 获取最新订阅链接\\n• 管理账号绑定状态');
    $footer = $this->getConfig('start_footer', '💡 提示：所有命令都需要在私聊中使用');

    $welcomeText = $welcomeTitle . "\n\n" . $botDescription . "\n\n";

    $user = User::where('telegram_id', $msg->chat_id)->first();
    if ($user) {
      $welcomeText .= "✅ 您已绑定账号：{$user->email}\n\n";
      $welcomeText .= $this->getConfig('start_unbind_guide', '📋 可用命令：\\n/traffic - 查看流量使用情况\\n/getlatesturl - 获取订阅链接\\n/unbind - 解绑账号');
    } else {
      $welcomeText .= $this->getConfig('start_bind_guide', '🔗 请先绑定您的 XBoard 账号：\\n1. 登录您的 XBoard 账户\\n2. 复制您的订阅链接\\n3. 发送 /bind + 订阅链接') . "\n\n";
      $welcomeText .= $this->getConfig('start_bind_commands', '📋 可用命令：\\n/bind [订阅链接] - 绑定账号');
    }

    $welcomeText .= "\n\n" . $footer;
    $welcomeText = str_replace('\\n', "\n", $welcomeText);

    $this->sendMessage($msg, $welcomeText);
  }

  public function handleMessage(bool $handled, array $data): bool
  {
    list($msg) = $data;
    if ($handled)
      return $handled;

    try {
      return match ($msg->message_type) {
        'message' => $this->handleCommandMessage($msg),
        'reply_message' => $this->handleReplyMessage($msg),
        'callback_query' => $this->handleTicketCallback($msg),
        default => false
      };
    } catch (\Exception $e) {
      Log::error('Telegram 命令处理意外错误', [
        'command' => $msg->command ?? 'unknown',
        'chat_id' => $msg->chat_id ?? 'unknown',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ]);

      if (isset($msg->chat_id)) {
        $this->telegramService->sendMessage($msg->chat_id, '系统繁忙，请稍后重试');
      }

      return true;
    }
  }

  protected function handleCommandMessage(object $msg): bool
  {
    if (!isset($this->commands['commands'][$msg->command])) {
      return false;
    }

    call_user_func($this->commands['commands'][$msg->command], $msg);
    return true;
  }

  protected function handleReplyMessage(object $msg): bool
  {
    if (!isset($this->commands['replies'])) {
      return false;
    }

    foreach ($this->commands['replies'] as $regex => $handler) {
      if (preg_match($regex, $msg->reply_text, $matches)) {
        call_user_func($handler, $msg, $matches);
        return true;
      }
    }

    return false;
  }

  public function handleUnknownCommand(array $data): void
  {
    list($msg) = $data;
    if (!$msg->is_private || $msg->message_type !== 'message')
      return;

    $helpText = $this->getConfig('help_text', '未知命令，请查看帮助');
    $this->telegramService->sendMessage($msg->chat_id, $helpText);
  }

  public function handleError(array $data): void
  {
    list($msg, $e) = $data;
    Log::error('Telegram 消息处理错误', [
      'chat_id' => $msg->chat_id ?? 'unknown',
      'command' => $msg->command ?? 'unknown',
      'message_type' => $msg->message_type ?? 'unknown',
      'error' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ]);
  }

  public function handleBindCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $subscribeUrl = $msg->args[0] ?? null;
    if (!$subscribeUrl) {
      $this->sendMessage($msg, '参数有误，请携带订阅地址发送');
      return;
    }

    $token = $this->extractTokenFromUrl($subscribeUrl);
    if (!$token) {
      $this->sendMessage($msg, '订阅地址无效');
      return;
    }

    $user = User::where('token', $token)->first();
    if (!$user) {
      $this->sendMessage($msg, '用户不存在');
      return;
    }

    if ($user->telegram_id) {
      $this->sendMessage($msg, '该账号已经绑定了Telegram账号');
      return;
    }

    $user->telegram_id = $msg->chat_id;
    if (!$user->save()) {
      $this->sendMessage($msg, '设置失败');
      return;
    }

    HookManager::call('user.telegram.bind.after', [$user]);
    $this->sendMessage($msg, '绑定成功');
  }

  protected function extractTokenFromUrl(string $url): ?string
  {
    $parsedUrl = parse_url($url);

    if (isset($parsedUrl['query'])) {
      parse_str($parsedUrl['query'], $query);
      if (isset($query['token'])) {
        return $query['token'];
      }
    }

    if (isset($parsedUrl['path'])) {
      $pathParts = explode('/', trim($parsedUrl['path'], '/'));
      $lastPart = end($pathParts);
      return $lastPart ?: null;
    }

    return null;
  }

  public function handleTrafficCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $user = $this->getBoundUser($msg);
    if (!$user) {
      return;
    }

    $transferUsed = $user->u + $user->d;
    $transferTotal = $user->transfer_enable;
    $transferRemaining = $transferTotal - $transferUsed;
    $usagePercentage = $transferTotal > 0 ? ($transferUsed / $transferTotal) * 100 : 0;

    $text = sprintf(
      "📊 流量使用情况\n\n已用流量：%sG\n总流量：%sG\n剩余流量：%sG\n使用率：%.2f%%",
      $this->transferToGBString($transferUsed),
      $this->transferToGBString($transferTotal),
      $this->transferToGBString($transferRemaining),
      $usagePercentage
    );

    $this->sendMessage($msg, $text);
  }

  public function handleGetLatestUrlCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $user = $this->getBoundUser($msg);
    if (!$user) {
      return;
    }

    $subscribeUrl = Helper::getSubscribeUrl($user->token);
    $text = sprintf("🔗 您的订阅链接：\n\n%s", $subscribeUrl);

    $this->sendMessage($msg, $text);
  }

  public function handleUnbindCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $user = $this->getBoundUser($msg);
    if (!$user) {
      return;
    }

    $user->telegram_id = null;
    if (!$user->save()) {
      $this->sendMessage($msg, '解绑失败');
      return;
    }

    $this->sendMessage($msg, '解绑成功');
  }

  public function handleTicketReply(object $msg, array $matches): void
  {
    $operator = $this->getTicketOperator($msg);
    if (!$operator) {
      return;
    }

    if (!isset($matches[2]) || !is_numeric($matches[2])) {
      Log::warning('Telegram 工单回复正则未匹配到工单ID', ['matches' => $matches, 'msg' => $msg]);
      $this->sendMessage($msg, '未能识别工单ID，请直接回复工单提醒消息。');
      return;
    }

    $ticketId = (int) $matches[2];
    $ticket = Ticket::where('id', $ticketId)->first();
    if (!$ticket) {
      $this->sendMessage($msg, '工单不存在');
      return;
    }

    if ($this->isTicketHistoryRequest($msg->text)) {
      $this->showTicketHistory($msg, $ticketId);
      return;
    }

    $ticketService = new TicketService();
    $ticketService->replyByAdmin(
      $ticketId,
      $this->extractTicketReplyText($msg->text),
      $operator->id
    );

    $this->sendMessage($msg, "工单 #{$ticketId} 回复成功");
  }

  public function handleTicketCommand(object $msg): void
  {
    if (!$this->getTicketOperator($msg)) {
      return;
    }

    $ticketId = isset($msg->args[0]) ? (int) $msg->args[0] : 0;
    $page = isset($msg->args[1]) ? max(1, (int) $msg->args[1]) : 1;

    if ($ticketId <= 0) {
      $this->sendMessage($msg, '用法：/ticket 工单ID，例如 /ticket 230');
      return;
    }

    $this->showTicketHistory($msg, $ticketId, $page);
  }

  protected function handleTicketCallback(object $msg): bool
  {
    if (!str_starts_with($msg->callback_data, 'ticket:')) {
      return false;
    }

    $parts = explode(':', $msg->callback_data);
    $action = $parts[1] ?? '';
    $ticketId = isset($parts[2]) ? (int) $parts[2] : 0;

    if ($ticketId <= 0) {
      $this->answerCallback($msg, '工单参数无效', true);
      return true;
    }

    match ($action) {
      'view' => $this->showTicketHistory($msg, $ticketId, max(1, (int) ($parts[3] ?? 1))),
      'reply' => $this->requestTicketReply($msg, $ticketId),
      'close' => $this->confirmTicketClose($msg, $ticketId),
      'close_confirm' => $this->closeTicketFromTelegram($msg, $ticketId),
      'close_cancel' => $this->cancelTicketClose($msg),
      default => $this->answerCallback($msg, '未知工单操作', true),
    };

    return true;
  }

  private function getTicketOperator(object $msg): ?User
  {
    if (!isset($msg->from_id)) {
      $this->sendMessage($msg, '无法识别 Telegram 操作人');
      return null;
    }

    $operator = User::where('telegram_id', $msg->from_id)
      ->where(fn($query) => $query->where('is_admin', 1)->orWhere('is_staff', 1))
      ->first();

    if (!$operator) {
      $this->sendMessage($msg, '你没有处理工单的权限');
      return null;
    }

    return $operator;
  }

  private function findTicketForTelegram(object $msg, int $ticketId): ?Ticket
  {
    $ticket = Ticket::with(['user', 'messages.user'])->find($ticketId);
    if (!$ticket) {
      $this->answerCallback($msg, '工单不存在', true);
      $this->sendMessage($msg, "工单 #{$ticketId} 不存在");
      return null;
    }

    return $ticket;
  }

  private function showTicketHistory(object $msg, int $ticketId, int $page = 1): void
  {
    if (!$this->getTicketOperator($msg)) {
      $this->answerCallback($msg, '没有权限', true);
      return;
    }

    $ticket = $this->findTicketForTelegram($msg, $ticketId);
    if (!$ticket) {
      return;
    }

    $messages = $ticket->messages
      ->sortBy('created_at')
      ->values();
    $total = $messages->count();
    $totalPages = max(1, (int) ceil($total / self::TICKET_HISTORY_PAGE_SIZE));
    $page = min(max(1, $page), $totalPages);
    $pageMessages = $messages->forPage($page, self::TICKET_HISTORY_PAGE_SIZE);

    $history = "📮 *工单 #{$ticket->id} 记录* ({$page}/{$totalPages})\n";
    $history .= "主题: {$this->cleanTelegramText($ticket->subject)}\n";
    $history .= "状态: " . (Ticket::$statusMap[$ticket->status] ?? $ticket->status) . "\n";
    $history .= "用户: {$this->cleanTelegramText($ticket->user?->email ?? '未知用户')}\n";
    $history .= "━━━━━━━━━━━━━━━━━━━━\n";

    foreach ($pageMessages as $ticketMessage) {
      $author = $ticketMessage->user_id === $ticket->user_id
        ? '用户'
        : ($ticketMessage->user?->is_admin ? '管理员' : '客服');
      $time = date('Y-m-d H:i:s', (int) $ticketMessage->created_at);
      $content = $this->cleanTelegramText($ticketMessage->message);
      $history .= "[{$time}] {$author}\n{$content}\n\n";
    }

    $options = $this->buildTicketHistoryKeyboard($ticketId, $page, $totalPages);
    $this->answerCallback($msg, '已打开工单记录');

    if ($msg->message_type === 'callback_query' && $msg->message_id) {
      $this->telegramService->editMessageText($msg->chat_id, $msg->message_id, trim($history), 'markdown', $options);
      return;
    }

    $this->telegramService->sendMessage($msg->chat_id, trim($history), 'markdown', $options);
  }

  private function requestTicketReply(object $msg, int $ticketId): void
  {
    if (!$this->getTicketOperator($msg)) {
      $this->answerCallback($msg, '没有权限', true);
      return;
    }

    if (!$this->findTicketForTelegram($msg, $ticketId)) {
      return;
    }

    $this->answerCallback($msg, '请回复提示消息');
    $this->telegramService->sendMessage($msg->chat_id, "请回复这条消息发送工单回复\n工单ID: {$ticketId}", '', [
      'reply_markup' => [
        'force_reply' => true,
        'input_field_placeholder' => '输入回复内容',
      ],
    ]);
  }

  private function confirmTicketClose(object $msg, int $ticketId): void
  {
    if (!$this->getTicketOperator($msg)) {
      $this->answerCallback($msg, '没有权限', true);
      return;
    }

    if (!$this->findTicketForTelegram($msg, $ticketId)) {
      return;
    }

    $this->answerCallback($msg, '请确认关闭');
    $this->telegramService->sendMessage($msg->chat_id, "确认关闭工单 #{$ticketId}？", 'markdown', [
      'reply_markup' => [
        'inline_keyboard' => [
          [
            ['text' => '确认关闭', 'callback_data' => "ticket:close_confirm:{$ticketId}"],
            ['text' => '取消', 'callback_data' => "ticket:close_cancel:{$ticketId}"],
          ],
        ],
      ],
    ]);
  }

  private function closeTicketFromTelegram(object $msg, int $ticketId): void
  {
    if (!$this->getTicketOperator($msg)) {
      $this->answerCallback($msg, '没有权限', true);
      return;
    }

    $ticket = $this->findTicketForTelegram($msg, $ticketId);
    if (!$ticket) {
      return;
    }

    $ticket->status = Ticket::STATUS_CLOSED;
    $ticket->save();

    $this->answerCallback($msg, '工单已关闭');
    $this->sendMessage($msg, "工单 #{$ticketId} 已关闭");
  }

  private function cancelTicketClose(object $msg): void
  {
    $this->answerCallback($msg, '已取消');
    $this->sendMessage($msg, '已取消关闭工单');
  }

  private function buildTicketActionKeyboard(int $ticketId): array
  {
    return [
      'reply_markup' => [
        'inline_keyboard' => [
          [
            ['text' => '查看记录', 'callback_data' => "ticket:view:{$ticketId}:1"],
            ['text' => '回复', 'callback_data' => "ticket:reply:{$ticketId}"],
            ['text' => '关闭', 'callback_data' => "ticket:close:{$ticketId}"],
          ],
        ],
      ],
    ];
  }

  private function buildTicketHistoryKeyboard(int $ticketId, int $page, int $totalPages): array
  {
    $buttons = [];
    if ($page > 1) {
      $previousPage = $page - 1;
      $buttons[] = ['text' => '上一页', 'callback_data' => "ticket:view:{$ticketId}:{$previousPage}"];
    }
    if ($page < $totalPages) {
      $nextPage = $page + 1;
      $buttons[] = ['text' => '下一页', 'callback_data' => "ticket:view:{$ticketId}:{$nextPage}"];
    }

    $buttons[] = ['text' => '回复', 'callback_data' => "ticket:reply:{$ticketId}"];
    $buttons[] = ['text' => '关闭', 'callback_data' => "ticket:close:{$ticketId}"];

    return [
      'reply_markup' => [
        'inline_keyboard' => [$buttons],
      ],
    ];
  }

  private function extractTicketReplyText(string $text): string
  {
    return trim($text);
  }

  private function isTicketHistoryRequest(string $text): bool
  {
    return in_array(trim($text), ['记录', '历史', '查看记录', '查看历史'], true);
  }

  private function cleanTelegramText(?string $text): string
  {
    $text = trim((string) $text);
    $text = str_replace(['`', '*'], ["'", ''], $text);

    return mb_strlen($text) > 800 ? mb_substr($text, 0, 797) . '...' : $text;
  }

  private function answerCallback(object $msg, string $text = '', bool $showAlert = false): void
  {
    if (!isset($msg->callback_query_id)) {
      return;
    }

    $this->telegramService->answerCallbackQuery($msg->callback_query_id, $text, $showAlert);
  }

  /**
   * 添加 Bot 命令到命令列表
   */
  public function addBotCommands(array $commands): array
  {
    foreach ($this->commandConfigs as $command => $config) {
      $commands[] = [
        'command' => $command,
        'description' => $config['description']
      ];
    }

    return $commands;
  }

  private function transferToGBString(float $transfer_enable, int $decimals = 2): string
  {
    return number_format(Helper::transferToGB($transfer_enable), $decimals, '.', '');
  }

  private function sendAdminNotification(string $message, array $options = []): void
  {
    $this->telegramService->sendMessageWithAdmin($message, true, $options);
  }

}
