<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfigSave;
use App\Protocols\Clash;
use App\Protocols\ClashMeta;
use App\Protocols\SingBox;
use App\Protocols\Stash;
use App\Protocols\Surfboard;
use App\Protocols\Surge;
use App\Services\MailService;
use App\Services\TelegramService;
use App\Services\ThemeService;
use App\Utils\Dict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ConfigController extends Controller
{


    public function getEmailTemplate()
    {
        $path = resource_path('views/mail/');
        $files = array_map(function ($item) use ($path) {
            return str_replace($path, '', $item);
        }, glob($path . '*'));
        return $this->success($files);
    }

    public function getThemeTemplate()
    {
        $path = public_path('theme/');
        $files = array_map(function ($item) use ($path) {
            return str_replace($path, '', $item);
        }, glob($path . '*'));
        return $this->success($files);
    }

    public function testSendMail(Request $request)
    {
        $mailLog = MailService::sendEmail([
            'email' => $request->user()->email,
            'subject' => 'This is xboard test email',
            'template_name' => 'notify',
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'content' => 'This is xboard test email',
                'url' => admin_setting('app_url')
            ]
        ]);
        return response([
            'data' => $mailLog,
        ]);
    }
    /**
     * 获取规则模板内容
     * 
     * @param string $file 文件路径
     * @return string 文件内容
     */
    private function getTemplateContent(string $file): string
    {
        $path = base_path($file);
        return File::exists($path) ? File::get($path) : '';
    }

    public function setTelegramWebhook(Request $request)
    {
        // 判断站点网址
        $app_url = admin_setting('app_url');
        if (blank($app_url))
            return $this->fail([422, '请先设置站点网址']);
        $hookUrl = $app_url . '/api/v1/guest/telegram/webhook?' . http_build_query([
            'access_token' => md5(admin_setting('telegram_bot_token', $request->input('telegram_bot_token')))
        ]);
        $telegramService = new TelegramService($request->input('telegram_bot_token'));
        $telegramService->getMe();
        $telegramService->setWebhook($hookUrl);
        return $this->success(true);
    }

    public function fetch(Request $request)
    {
        $key = $request->input('key');
        
        // 构建配置数据映射
        $configMappings = $this->getConfigMappings();
        
        // 如果请求特定分组，直接返回
        if ($key && isset($configMappings[$key])) {
            return $this->success([$key => $configMappings[$key]]);
        }
        
        return $this->success($configMappings);
    }

    /**
     * 获取配置映射数据
     * 
     * @return array 配置映射数组
     */
    private function getConfigMappings(): array
    {
        return [
            'invite' => [
                'invite_force' => (bool) admin_setting('invite_force', 0),
                'invite_commission' => admin_setting('invite_commission', 10),
                'invite_gen_limit' => admin_setting('invite_gen_limit', 5),
                'invite_never_expire' => (bool) admin_setting('invite_never_expire', 0),
                'commission_first_time_enable' => (bool) admin_setting('commission_first_time_enable', 1),
                'commission_auto_check_enable' => (bool) admin_setting('commission_auto_check_enable', 1),
                'commission_withdraw_limit' => admin_setting('commission_withdraw_limit', 100),
                'commission_withdraw_method' => admin_setting('commission_withdraw_method', Dict::WITHDRAW_METHOD_WHITELIST_DEFAULT),
                'withdraw_close_enable' => (bool) admin_setting('withdraw_close_enable', 0),
                'commission_distribution_enable' => (bool) admin_setting('commission_distribution_enable', 0),
                'commission_distribution_l1' => admin_setting('commission_distribution_l1'),
                'commission_distribution_l2' => admin_setting('commission_distribution_l2'),
                'commission_distribution_l3' => admin_setting('commission_distribution_l3')
            ],
            'site' => [
                'logo' => admin_setting('logo'),
                'force_https' => (int) admin_setting('force_https', 0),
                'stop_register' => (int) admin_setting('stop_register', 0),
                'app_name' => admin_setting('app_name', 'XBoard'),
                'app_description' => admin_setting('app_description', 'XBoard is best!'),
                'app_url' => admin_setting('app_url'),
                'subscribe_url' => admin_setting('subscribe_url'),
                'try_out_plan_id' => (int) admin_setting('try_out_plan_id', 0),
                'try_out_hour' => (int) admin_setting('try_out_hour', 1),
                'tos_url' => admin_setting('tos_url'),
                'currency' => admin_setting('currency', 'CNY'),
                'currency_symbol' => admin_setting('currency_symbol', '¥'),
            ],
            'subscribe' => [
                'plan_change_enable' => (bool) admin_setting('plan_change_enable', 1),
                'reset_traffic_method' => (int) admin_setting('reset_traffic_method', 0),
                'surplus_enable' => (bool) admin_setting('surplus_enable', 1),
                'new_order_event_id' => (int) admin_setting('new_order_event_id', 0),
                'renew_order_event_id' => (int) admin_setting('renew_order_event_id', 0),
                'change_order_event_id' => (int) admin_setting('change_order_event_id', 0),
                'show_info_to_server_enable' => (bool) admin_setting('show_info_to_server_enable', 0),
                'show_protocol_to_server_enable' => (bool) admin_setting('show_protocol_to_server_enable', 0),
                'default_remind_expire' => (bool) admin_setting('default_remind_expire', 1),
                'default_remind_traffic' => (bool) admin_setting('default_remind_traffic', 1),
                'subscribe_path' => admin_setting('subscribe_path', 's'),
            ],
            'frontend' => [
                'frontend_theme' => admin_setting('frontend_theme', 'Xboard'),
                'frontend_theme_sidebar' => admin_setting('frontend_theme_sidebar', 'light'),
                'frontend_theme_header' => admin_setting('frontend_theme_header', 'dark'),
                'frontend_theme_color' => admin_setting('frontend_theme_color', 'default'),
                'frontend_background_url' => admin_setting('frontend_background_url'),
            ],
            'server' => [
                'server_token' => admin_setting('server_token'),
                'server_pull_interval' => admin_setting('server_pull_interval', 60),
                'server_push_interval' => admin_setting('server_push_interval', 60),
                'device_limit_mode' => (int) admin_setting('device_limit_mode', 0),
            ],
            'email' => [
                'email_template' => admin_setting('email_template', 'default'),
                'email_host' => admin_setting('email_host'),
                'email_port' => admin_setting('email_port'),
                'email_username' => admin_setting('email_username'),
                'email_password' => admin_setting('email_password'),
                'email_encryption' => admin_setting('email_encryption'),
                'email_from_address' => admin_setting('email_from_address'),
                'remind_mail_enable' => (bool) admin_setting('remind_mail_enable', false),
            ],
            'telegram' => [
                'telegram_bot_enable' => (bool) admin_setting('telegram_bot_enable', 0),
                'telegram_bot_token' => admin_setting('telegram_bot_token'),
                'telegram_discuss_link' => admin_setting('telegram_discuss_link')
            ],
            'app' => [
                'windows_version' => admin_setting('windows_version', ''),
                'windows_download_url' => admin_setting('windows_download_url', ''),
                'macos_version' => admin_setting('macos_version', ''),
                'macos_download_url' => admin_setting('macos_download_url', ''),
                'android_version' => admin_setting('android_version', ''),
                'android_download_url' => admin_setting('android_download_url', '')
            ],
            'safe' => [
                'email_verify' => (bool) admin_setting('email_verify', 0),
                'safe_mode_enable' => (bool) admin_setting('safe_mode_enable', 0),
                'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))),
                'email_whitelist_enable' => (bool) admin_setting('email_whitelist_enable', 0),
                'email_whitelist_suffix' => admin_setting('email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT),
                'email_gmail_limit_enable' => (bool) admin_setting('email_gmail_limit_enable', 0),
                'recaptcha_enable' => (bool) admin_setting('recaptcha_enable', 0),
                'recaptcha_key' => admin_setting('recaptcha_key', ''),
                'recaptcha_site_key' => admin_setting('recaptcha_site_key', ''),
                'register_limit_by_ip_enable' => (bool) admin_setting('register_limit_by_ip_enable', 0),
                'register_limit_count' => admin_setting('register_limit_count', 3),
                'register_limit_expire' => admin_setting('register_limit_expire', 60),
                'password_limit_enable' => (bool) admin_setting('password_limit_enable', 1),
                'password_limit_count' => admin_setting('password_limit_count', 5),
                'password_limit_expire' => admin_setting('password_limit_expire', 60)
            ],
            'subscribe_template' => [
                'subscribe_template_singbox' => $this->formatTemplateContent(
                    admin_setting('subscribe_template_singbox', $this->getDefaultTemplate('singbox')),
                    'json'
                ),
                'subscribe_template_clash' => admin_setting('subscribe_template_clash', $this->getDefaultTemplate('clash')),
                'subscribe_template_clashmeta' => admin_setting('subscribe_template_clashmeta', $this->getDefaultTemplate('clashmeta')),
                'subscribe_template_stash' => admin_setting('subscribe_template_stash', $this->getDefaultTemplate('stash')),
                'subscribe_template_surge' => admin_setting('subscribe_template_surge', $this->getDefaultTemplate('surge')),
                'subscribe_template_surfboard' => admin_setting('subscribe_template_surfboard', $this->getDefaultTemplate('surfboard'))
            ]
        ];
    }

    public function save(ConfigSave $request)
    {
        $data = $request->validated();

        foreach ($data as $k => $v) {
            if ($k == 'frontend_theme') {
                $themeService = app(ThemeService::class);
                $themeService->switch($v);
            }
            admin_setting([$k => $v]);
        }
        
        return $this->success(true);
    }

    /**
     * 格式化模板内容
     * 
     * @param mixed $content 模板内容
     * @param string $format 输出格式 (json|string)
     * @return string 格式化后的内容
     */
    private function formatTemplateContent(mixed $content, string $format = 'string'): string
    {
        return match ($format) {
            'json' => match (true) {
                is_array($content) => json_encode(
                    value: $content, 
                    flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                
                is_string($content) && str($content)->isJson() => rescue(
                    callback: fn() => json_encode(
                        value: json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR), 
                        flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    rescue: $content,
                    report: false
                ),
                
                default => str($content)->toString()
            },
            
            default => str($content)->toString()
        };
    }

    /**
     * 获取默认模板内容
     * 
     * @param string $type 模板类型
     * @return string 默认模板内容
     */
    private function getDefaultTemplate(string $type): string
    {
        $fileMap = [
            'singbox' => [SingBox::CUSTOM_TEMPLATE_FILE, SingBox::DEFAULT_TEMPLATE_FILE],
            'clash' => [Clash::CUSTOM_TEMPLATE_FILE, Clash::DEFAULT_TEMPLATE_FILE],
            'clashmeta' => [
                ClashMeta::CUSTOM_TEMPLATE_FILE,
                ClashMeta::CUSTOM_CLASH_TEMPLATE_FILE,
                ClashMeta::DEFAULT_TEMPLATE_FILE
            ],
            'stash' => [
                Stash::CUSTOM_TEMPLATE_FILE,
                Stash::CUSTOM_CLASH_TEMPLATE_FILE,
                Stash::DEFAULT_TEMPLATE_FILE
            ],
            'surge' => [Surge::CUSTOM_TEMPLATE_FILE, Surge::DEFAULT_TEMPLATE_FILE],
            'surfboard' => [Surfboard::CUSTOM_TEMPLATE_FILE, Surfboard::DEFAULT_TEMPLATE_FILE],
        ];

        if (!isset($fileMap[$type])) {
            return '';
        }

        // 按优先级查找可用的模板文件
        foreach ($fileMap[$type] as $file) {
            $content = $this->getTemplateContent($file);
            if (!empty($content)) {
                // 对于 SingBox，需要格式化 JSON
                if ($type === 'singbox') {
                    $decoded = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                return $content;
            }
        }

        return '';
    }
}
