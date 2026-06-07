const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('Telegram webhook parses callback query ticket action payloads', () => {
  const controller = readRepoFile('app/Http/Controllers/V1/Guest/TelegramController.php');

  assert.match(controller, /formatCallbackQuery\(\$data\)/);
  assert.match(controller, /\$data\['callback_query'\]/);
  assert.match(controller, /callback_query_id/);
  assert.match(controller, /callback_data/);
  assert.match(controller, /from_id/);
  assert.match(controller, /message_type'\s*=>\s*'callback_query'/);
});

test('Telegram service and queue job support structured message options', () => {
  const service = readRepoFile('app/Services/TelegramService.php');
  const job = readRepoFile('app/Jobs/SendTelegramJob.php');

  assert.match(service, /function sendMessage\(int \$chatId, string \$text, string \$parseMode = '', array \$options = \[\]\)/);
  assert.match(service, /array_filter\(\s*array_merge\(/);
  assert.match(service, /answerCallbackQuery/);
  assert.match(job, /protected array \$options/);
  assert.match(job, /__construct\(int \$telegramId, string \$text, array \$options = \[\]\)/);
  assert.match(job, /sendMessage\(\$this->telegramId, \$this->text, 'markdown', \$this->options\)/);
});

test('Telegram ticket reminder includes inline action buttons', () => {
  const plugin = readRepoFile('plugins/Telegram/Plugin.php');

  assert.match(plugin, /buildTicketActionKeyboard\(int \$ticketId\)/);
  assert.match(plugin, /inline_keyboard/);
  assert.match(plugin, /ticket:view:\{\$ticketId\}:1/);
  assert.match(plugin, /ticket:reply:\{\$ticketId\}/);
  assert.match(plugin, /ticket:close:\{\$ticketId\}/);
  assert.match(plugin, /sendAdminNotification\(\$TGmessage, \$this->buildTicketActionKeyboard\(\$ticket->id\)\)/);
});

test('Telegram ticket actions verify admin or staff identity by callback sender', () => {
  const plugin = readRepoFile('plugins/Telegram/Plugin.php');

  assert.match(plugin, /private function getTicketOperator\(object \$msg\): \?User/);
  assert.match(plugin, /User::where\('telegram_id', \$msg->from_id\)/);
  assert.match(plugin, /where\(fn\(\$query\) => \$query->where\('is_admin', 1\)->orWhere\('is_staff', 1\)\)/);
  assert.doesNotMatch(plugin, /User::where\('telegram_id', \$msg->chat_id\)->where\(fn\(\$query\) => \$query->where\('is_admin', 1\)->orWhere\('is_staff', 1\)\)/);
});

test('Telegram ticket history is paginated with compact callback data', () => {
  const plugin = readRepoFile('plugins/Telegram/Plugin.php');

  assert.match(plugin, /private const TICKET_HISTORY_PAGE_SIZE = 5/);
  assert.match(plugin, /handleTicketCallback\(object \$msg\)/);
  assert.match(plugin, /showTicketHistory\(object \$msg, int \$ticketId, int \$page = 1\)/);
  assert.match(plugin, /forPage\(\$page, self::TICKET_HISTORY_PAGE_SIZE\)/);
  assert.match(plugin, /ticket:view:\{\$ticketId\}:\{\$previousPage\}/);
  assert.match(plugin, /ticket:view:\{\$ticketId\}:\{\$nextPage\}/);
});

test('Telegram ticket history can be requested without inline buttons', () => {
  const plugin = readRepoFile('plugins/Telegram/Plugin.php');

  assert.match(plugin, /'\/ticket'\s*=>\s*\['description'\s*=>\s*'查看工单记录',\s*'handler'\s*=>\s*'handleTicketCommand'\]/);
  assert.match(plugin, /public function handleTicketCommand\(object \$msg\): void/);
  assert.match(plugin, /showTicketHistory\(\$msg, \$ticketId, \$page\)/);
  assert.match(plugin, /private function isTicketHistoryRequest\(string \$text\): bool/);
  assert.match(plugin, /if \(\$this->isTicketHistoryRequest\(\$msg->text\)\) \{[\s\S]*showTicketHistory\(\$msg, \$ticketId\);[\s\S]*return;/);
  assert.match(plugin, /\$msg->message_type === 'callback_query' && \$msg->message_id/);
});

test('Telegram ticket reply uses ForceReply and writes through TicketService', () => {
  const plugin = readRepoFile('plugins/Telegram/Plugin.php');

  assert.match(plugin, /requestTicketReply\(object \$msg, int \$ticketId\)/);
  assert.match(plugin, /回复本消息即可回复工单/);
  assert.match(plugin, /force_reply/);
  assert.match(plugin, /工单ID: \{\$ticketId\}/);
  assert.match(plugin, /replyByAdmin\(\s*\$ticketId,\s*\$this->extractTicketReplyText\(\$msg->text\),\s*\$operator->id\s*\)/);
});

test('Telegram ticket close action requires confirmation before closing', () => {
  const plugin = readRepoFile('plugins/Telegram/Plugin.php');

  assert.match(plugin, /confirmTicketClose\(object \$msg, int \$ticketId\)/);
  assert.match(plugin, /ticket:close_confirm:\{\$ticketId\}/);
  assert.match(plugin, /ticket:close_cancel:\{\$ticketId\}/);
  assert.match(plugin, /closeTicketFromTelegram\(object \$msg, int \$ticketId\)/);
  assert.match(plugin, /\$ticket->status = Ticket::STATUS_CLOSED/);
});

test('Ticket messages expose their author for Telegram history labels', () => {
  const model = readRepoFile('app/Models/TicketMessage.php');

  assert.match(model, /function user\(\): BelongsTo/);
  assert.match(model, /belongsTo\(User::class, 'user_id', 'id'\)/);
});
