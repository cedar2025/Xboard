<?php

namespace App\Http\Controllers\V1\Passport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passport\CommSendEmailVerify;
use App\Jobs\SendEmailJob;
use App\Models\InviteCode;
use App\Models\User;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ReCaptcha\ReCaptcha;

class CommController extends Controller
{

    public function sendEmailVerify(CommSendEmailVerify $request)
    {
        if ((int) admin_setting('recaptcha_enable', 0)) {
            $recaptcha = new ReCaptcha(admin_setting('recaptcha_key'));
            $recaptchaResp = $recaptcha->verify($request->input('recaptcha_data'));
            if (!$recaptchaResp->isSuccess()) {
                return $this->fail([400, __('Invalid code is incorrect')]);
            }
        }

        $email = $request->input('email');

        // 检查白名单后缀限制
        if ((int) admin_setting('email_whitelist_enable', 0)) {
            $isRegisteredEmail = User::where('email', $email)->exists();
            if (!$isRegisteredEmail) {
                $allowedSuffixes = Helper::getEmailSuffix();
                $emailSuffix = substr(strrchr($email, '@'), 1);

                if (!in_array($emailSuffix, $allowedSuffixes)) {
                    return $this->fail([400, __('Email suffix is not in whitelist')]);
                }
            }
        }

        if (Cache::get(CacheKey::get('LAST_SEND_EMAIL_VERIFY_TIMESTAMP', $email))) {
            return $this->fail([400, __('Email verification code has been sent, please request again later')]);
        }
        $code = rand(100000, 999999);
        $subject = admin_setting('app_name', 'XBoard') . __('Email verification code');

        SendEmailJob::dispatch([
            'email' => $email,
            'subject' => $subject,
            'template_name' => 'verify',
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'code' => $code,
                'url' => admin_setting('app_url')
            ]
        ]);

        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', $email), $code, 300);
        Cache::put(CacheKey::get('LAST_SEND_EMAIL_VERIFY_TIMESTAMP', $email), time(), 60);
        return $this->success(true);
    }

    public function pv(Request $request)
    {
        $inviteCode = InviteCode::where('code', $request->input('invite_code'))->first();
        if ($inviteCode) {
            $inviteCode->pv = $inviteCode->pv + 1;
            $inviteCode->save();
        }

        return $this->success(true);
    }

}
