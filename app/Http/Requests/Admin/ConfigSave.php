<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfigSave extends FormRequest
{
    const RULES = [
        // invite & commission
        'invite_force' => '',
        'invite_commission' => 'integer|nullable',
        'invite_gen_limit' => 'integer|nullable',
        'invite_never_expire' => '',
        'commission_first_time_enable' => '',
        'commission_auto_check_enable' => '',
        'commission_withdraw_limit' => 'nullable|numeric',
        'commission_withdraw_method' => 'nullable|array',
        'withdraw_close_enable' => '',
        'commission_distribution_enable' => '',
        'commission_distribution_l1' => 'nullable|numeric',
        'commission_distribution_l2' => 'nullable|numeric',
        'commission_distribution_l3' => 'nullable|numeric',
        // site
        'logo' => 'nullable|url',
        'force_https' => '',
        'stop_register' => '',
        'app_name' => '',
        'app_description' => '',
        'app_url' => 'nullable|url',
        'subscribe_url' => 'nullable',
        'try_out_enable' => '',
        'try_out_plan_id' => 'integer',
        'try_out_hour' => 'numeric',
        'tos_url' => 'nullable|url',
        'currency' => '',
        'currency_symbol' => '',
        // subscribe
        'plan_change_enable' => '',
        'reset_traffic_method' => 'in:0,1,2,3,4',
        'surplus_enable' => '',
        'new_order_event_id' => '',
        'renew_order_event_id' => '',
        'change_order_event_id' => '',
        'show_info_to_server_enable' => '',
        'show_protocol_to_server_enable' => '',
        'subscribe_path' => '',
        // server
        'server_token' => 'nullable|min:16',
        'server_pull_interval' => 'integer',
        'server_push_interval' => 'integer',
        'device_limit_mode' => 'integer',
        // frontend
        'frontend_theme' => '',
        'frontend_theme_sidebar' => 'nullable|in:dark,light',
        'frontend_theme_header' => 'nullable|in:dark,light',
        'frontend_theme_color' => 'nullable|in:default,darkblue,black,green',
        'frontend_background_url' => 'nullable|url',
        // email
        'email_template' => '',
        'email_host' => '',
        'email_port' => '',
        'email_username' => '',
        'email_password' => '',
        'email_encryption' => '',
        'email_from_address' => '',
        'remind_mail_enable' => '',
        // telegram
        'telegram_bot_enable' => '',
        'telegram_bot_token' => '',
        'telegram_discuss_id' => '',
        'telegram_channel_id' => '',
        'telegram_discuss_link' => 'nullable|url',
        // app
        'windows_version' => '',
        'windows_download_url' => '',
        'macos_version' => '',
        'macos_download_url' => '',
        'android_version' => '',
        'android_download_url' => '',
        // safe
        'email_whitelist_enable' => 'boolean',
        'email_whitelist_suffix' => 'nullable|array',
        'email_gmail_limit_enable' => 'boolean',
        'recaptcha_enable' => 'boolean',
        'recaptcha_key' => '',
        'recaptcha_site_key' => '',
        'email_verify' => 'bool',
        'safe_mode_enable' => 'boolean',
        'register_limit_by_ip_enable' => 'boolean',
        'register_limit_count' => 'integer',
        'register_limit_expire' => 'integer',
        'secure_path' => 'min:8|regex:/^[\w-]*$/',
        'password_limit_enable' => 'boolean',
        'password_limit_count' => 'integer',
        'password_limit_expire' => 'integer',
        'default_remind_expire' => 'boolean',
        'default_remind_traffic' => 'boolean',
        'subscribe_template_singbox' => 'nullable',
        'subscribe_template_clash' => 'nullable',
        'subscribe_template_clashmeta' => 'nullable',
        'subscribe_template_stash' => 'nullable',
        'subscribe_template_surge' => 'nullable',
        'subscribe_template_surfboard' => 'nullable'
    ];
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return self::RULES;
    }

    public function messages()
    {
        // illiteracy prompt
        return [
            'app_url.url' => '站点URL格式不正确，必须携带http(s)://',
            'subscribe_url.url' => '订阅URL格式不正确，必须携带http(s)://',
            'server_token.min' => '通讯密钥长度必须大于16位',
            'tos_url.url' => '服务条款URL格式不正确，必须携带http(s)://',
            'telegram_discuss_link.url' => 'Telegram群组地址必须为URL格式，必须携带http(s)://',
            'logo.url' => 'LOGO URL格式不正确，必须携带https(s)://',
            'secure_path.min' => '后台路径长度最小为8位',
            'secure_path.regex' => '后台路径只能为字母或数字'
        ];
    }
}
