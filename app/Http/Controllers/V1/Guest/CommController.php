<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Services\Plugin\HookManager;
use App\Utils\Dict;
use App\Utils\Helper;
use Illuminate\Support\Facades\Http;

class CommController extends Controller
{
    public function config()
    {
        $data = [
            'tos_url' => admin_setting('tos_url'),
            'is_email_verify' => (int) admin_setting('email_verify', 0) ? 1 : 0,
            'is_invite_force' => (int) admin_setting('invite_force', 0) ? 1 : 0,
            'email_whitelist_suffix' => (int) admin_setting('email_whitelist_enable', 0)
                ? Helper::getEmailSuffix()
                : 0,
            'is_captcha' => (int) admin_setting('captcha_enable', 0) ? 1 : 0,
            'captcha_type' => admin_setting('captcha_type', 'recaptcha'),
            'recaptcha_site_key' => admin_setting('recaptcha_site_key'),
            'recaptcha_v3_site_key' => admin_setting('recaptcha_v3_site_key'),
            'recaptcha_v3_score_threshold' => admin_setting('recaptcha_v3_score_threshold', 0.5),
            'turnstile_site_key' => admin_setting('turnstile_site_key'),
            'app_description' => admin_setting('app_description'),
            'app_url' => admin_setting('app_url'),
            'logo' => admin_setting('logo'),
            // 保持向后兼容
            'is_recaptcha' => (int) admin_setting('captcha_enable', 0) ? 1 : 0,
        ];

        $data = HookManager::filter('guest_comm_config', $data);

        return $this->success($data);
    }
}
