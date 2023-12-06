<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Utils\Dict;
use Illuminate\Support\Facades\Http;

class CommController extends Controller
{
    public function config()
    {
        $data = [
            'tos_url' => admin_setting('tos_url'),
            'is_email_verify' => (int)admin_setting('email_verify', 0) ? 1 : 0,
            'is_invite_force' => (int)admin_setting('invite_force', 0) ? 1 : 0,
            'email_whitelist_suffix' => (int)admin_setting('email_whitelist_enable', 0)
                ? $this->getEmailSuffix()
                : 0,
            'is_recaptcha' => (int)admin_setting('recaptcha_enable', 0) ? 1 : 0,
            'recaptcha_site_key' => admin_setting('recaptcha_site_key'),
            'app_description' => admin_setting('app_description'),
            'app_url' => admin_setting('app_url'),
            'logo' => admin_setting('logo'),
        ];
        return $this->success($data);
    }

    private function getEmailSuffix()
    {
        $suffix = admin_setting('email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT);
        if (!is_array($suffix)) {
            return preg_split('/,/', $suffix);
        }
        return $suffix;
    }
}
