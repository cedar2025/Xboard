<?php

namespace App\Http\Controllers\V2\Client;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class AppController extends Controller
{
    public function getConfig(Request $request)
    {
        $config = [
            'app_info' => [
                'app_name' => admin_setting('app_name', 'XB加速器'), // 应用名称
                'app_description' => admin_setting('app_description', '专业的网络加速服务'), // 应用描述
                'app_url' => admin_setting('app_url', 'https://app.example.com'), // 应用官网 URL
                'logo' => admin_setting('logo', 'https://example.com/logo.png'), // 应用 Logo URL
                'version' => admin_setting('app_version', '1.0.0'), // 应用版本号
            ],
            'features' => [
                'enable_register' => (bool) admin_setting('app_enable_register', true), // 是否开启注册功能
                'enable_invite_system' => (bool) admin_setting('app_enable_invite_system', true), // 是否开启邀请系统
                'enable_telegram_bot' => (bool) admin_setting('telegram_bot_enable', false), // 是否开启 Telegram 机器人
                'enable_ticket_system' => (bool) admin_setting('app_enable_ticket_system', true), // 是否开启工单系统
                'ticket_must_wait_reply' => (bool) admin_setting('ticket_must_wait_reply', 0), // 工单是否需要等待管理员回复后才可继续发消息
                'enable_commission_system' => (bool) admin_setting('app_enable_commission_system', true), // 是否开启佣金系统
                'enable_traffic_log' => (bool) admin_setting('app_enable_traffic_log', true), // 是否开启流量日志
                'enable_knowledge_base' => (bool) admin_setting('app_enable_knowledge_base', true), // 是否开启知识库
                'enable_announcements' => (bool) admin_setting('app_enable_announcements', true), // 是否开启公告系统
                'enable_auto_renewal' => (bool) admin_setting('app_enable_auto_renewal', false), // 是否开启自动续费
                'enable_coupon_system' => (bool) admin_setting('app_enable_coupon_system', true), // 是否开启优惠券系统
                'enable_speed_test' => (bool) admin_setting('app_enable_speed_test', true), // 是否开启测速功能
                'enable_server_ping' => (bool) admin_setting('app_enable_server_ping', true), // 是否开启服务器延迟检测
            ],
            'ui_config' => [
                'theme' => [
                    'primary_color' => admin_setting('app_primary_color', '#00C851'), // 主色调 (十六进制)
                    'secondary_color' => admin_setting('app_secondary_color', '#007E33'), // 辅助色 (十六进制)
                    'accent_color' => admin_setting('app_accent_color', '#FF6B35'), // 强调色 (十六进制)
                    'background_color' => admin_setting('app_background_color', '#F5F5F5'), // 背景色 (十六进制)
                    'text_color' => admin_setting('app_text_color', '#333333'), // 文字色 (十六进制)
                ],
                'home_screen' => [
                    'show_speed_test' => (bool) admin_setting('app_show_speed_test', true), // 是否显示测速
                    'show_traffic_chart' => (bool) admin_setting('app_show_traffic_chart', true), // 是否显示流量图表
                    'show_server_ping' => (bool) admin_setting('app_show_server_ping', true), // 是否显示服务器延迟
                    'default_server_sort' => admin_setting('app_default_server_sort', 'ping'), // 默认服务器排序方式
                    'show_connection_status' => (bool) admin_setting('app_show_connection_status', true), // 是否显示连接状态
                ],
                'server_list' => [
                    'show_country_flags' => (bool) admin_setting('app_show_country_flags', true), // 是否显示国家旗帜
                    'show_ping_values' => (bool) admin_setting('app_show_ping_values', true), // 是否显示延迟值
                    'show_traffic_usage' => (bool) admin_setting('app_show_traffic_usage', true), // 是否显示流量使用
                    'group_by_country' => (bool) admin_setting('app_group_by_country', false), // 是否按国家分组
                    'show_server_status' => (bool) admin_setting('app_show_server_status', true), // 是否显示服务器状态
                ],
            ],
            'business_rules' => [
                'min_password_length' => (int) admin_setting('app_min_password_length', 8), // 最小密码长度
                'max_login_attempts' => (int) admin_setting('app_max_login_attempts', 5), // 最大登录尝试次数
                'session_timeout_minutes' => (int) admin_setting('app_session_timeout_minutes', 30), // 会话超时时间(分钟)
                'auto_disconnect_after_minutes' => (int) admin_setting('app_auto_disconnect_after_minutes', 60), // 自动断开连接时间(分钟)
                'max_concurrent_connections' => (int) admin_setting('app_max_concurrent_connections', 3), // 最大并发连接数
                'traffic_warning_threshold' => (float) admin_setting('app_traffic_warning_threshold', 0.8), // 流量警告阈值(0-1)
                'subscription_reminder_days' => admin_setting('app_subscription_reminder_days', [7, 3, 1]), // 订阅到期提醒天数
                'connection_timeout_seconds' => (int) admin_setting('app_connection_timeout_seconds', 10), // 连接超时时间(秒)
                'health_check_interval_seconds' => (int) admin_setting('app_health_check_interval_seconds', 30), // 健康检查间隔(秒)
            ],
            'server_config' => [
                'default_kernel' => admin_setting('app_default_kernel', 'clash'), // 默认内核 (clash/singbox)
                'auto_select_fastest' => (bool) admin_setting('app_auto_select_fastest', true), // 是否自动选择最快服务器
                'fallback_servers' => admin_setting('app_fallback_servers', ['server1', 'server2']), // 备用服务器列表
                'enable_auto_switch' => (bool) admin_setting('app_enable_auto_switch', true), // 是否开启自动切换
                'switch_threshold_ms' => (int) admin_setting('app_switch_threshold_ms', 1000), // 切换阈值(毫秒)
            ],
            'security_config' => [
                'tos_url' => admin_setting('tos_url', 'https://example.com/tos'), // 服务条款 URL
                'privacy_policy_url' => admin_setting('app_privacy_policy_url', 'https://example.com/privacy'), // 隐私政策 URL
                'is_email_verify' => (int) admin_setting('email_verify', 1), // 是否开启邮箱验证 (0/1)
                'is_invite_force' => (int) admin_setting('invite_force', 0), // 是否强制邀请码 (0/1)
                'email_whitelist_suffix' => (int) admin_setting('email_whitelist_suffix', 0), // 邮箱白名单后缀 (0/1)
                'is_captcha' => (int) admin_setting('captcha_enable', 1), // 是否开启验证码 (0/1)
                'captcha_type' => admin_setting('captcha_type', 'recaptcha'), // 验证码类型 (recaptcha/turnstile)
                'recaptcha_site_key' => admin_setting('recaptcha_site_key', '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'), // reCAPTCHA 站点密钥
                'recaptcha_v3_site_key' => admin_setting('recaptcha_v3_site_key', '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'), // reCAPTCHA v3 站点密钥
                'recaptcha_v3_score_threshold' => (float) admin_setting('recaptcha_v3_score_threshold', 0.5), // reCAPTCHA v3 分数阈值
                'turnstile_site_key' => admin_setting('turnstile_site_key', '0x4AAAAAAAABkMYinukE8nzUg'), // Turnstile 站点密钥
            ],
            'payment_config' => [
                'currency' => admin_setting('currency', 'CNY'), // 货币类型
                'currency_symbol' => admin_setting('currency_symbol', '¥'), // 货币符号
                'withdraw_methods' => admin_setting('app_withdraw_methods', ['alipay', 'wechat', 'bank']), // 提现方式列表
                'min_withdraw_amount' => (int) admin_setting('app_min_withdraw_amount', 100), // 最小提现金额(分)
                'withdraw_fee_rate' => (float) admin_setting('app_withdraw_fee_rate', 0.01), // 提现手续费率
            ],
            'notification_config' => [
                'enable_push_notifications' => (bool) admin_setting('app_enable_push_notifications', true), // 是否开启推送通知
                'enable_email_notifications' => (bool) admin_setting('app_enable_email_notifications', true), // 是否开启邮件通知
                'enable_sms_notifications' => (bool) admin_setting('app_enable_sms_notifications', false), // 是否开启短信通知
                'notification_schedule' => [
                    'traffic_warning' => (bool) admin_setting('app_notification_traffic_warning', true), // 流量警告通知
                    'subscription_expiry' => (bool) admin_setting('app_notification_subscription_expiry', true), // 订阅到期通知
                    'server_maintenance' => (bool) admin_setting('app_notification_server_maintenance', true), // 服务器维护通知
                    'promotional_offers' => (bool) admin_setting('app_notification_promotional_offers', false), // 促销优惠通知
                ],
            ],
            'cache_config' => [
                'config_cache_duration' => (int) admin_setting('app_config_cache_duration', 3600), // 配置缓存时长(秒)
                'server_list_cache_duration' => (int) admin_setting('app_server_list_cache_duration', 1800), // 服务器列表缓存时长(秒)
                'user_info_cache_duration' => (int) admin_setting('app_user_info_cache_duration', 900), // 用户信息缓存时长(秒)
            ],
            'last_updated' => time(), // 最后更新时间戳
        ];
        $config['config_hash'] = md5(json_encode($config)); // 配置哈希值(用于校验)

        $config = $config ?? [];
        return response()->json(['data' => $config]);
    }

    public function getVersion(Request $request)
    {
        if (
            strpos($request->header('user-agent'), 'tidalab/4.0.0') !== false
            || strpos($request->header('user-agent'), 'tunnelab/4.0.0') !== false
        ) {
            if (strpos($request->header('user-agent'), 'Win64') !== false) {
                $data = [
                    'version' => admin_setting('windows_version'),
                    'download_url' => admin_setting('windows_download_url')
                ];
            } else {
                $data = [
                    'version' => admin_setting('macos_version'),
                    'download_url' => admin_setting('macos_download_url')
                ];
            }
        } else {
            $data = [
                'windows_version' => admin_setting('windows_version'),
                'windows_download_url' => admin_setting('windows_download_url'),
                'macos_version' => admin_setting('macos_version'),
                'macos_download_url' => admin_setting('macos_download_url'),
                'android_version' => admin_setting('android_version'),
                'android_download_url' => admin_setting('android_download_url')
            ];
        }
        return $this->success($data);
    }
}
