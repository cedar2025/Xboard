<?php

namespace App\Http\Controllers\V1\Passport;

use App\Helpers\ResponseEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passport\AuthForget;
use App\Http\Requests\Passport\AuthLogin;
use App\Http\Requests\Passport\AuthRegister;
use App\Jobs\SendEmailJob;
use App\Models\InviteCode;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuthService;
use App\Utils\CacheKey;
use App\Utils\Dict;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ReCaptcha\ReCaptcha;

class AuthController extends Controller
{
    public function loginWithMailLink(Request $request)
    {
        if (!(int)admin_setting('login_with_mail_link_enable')) {
            return $this->fail([404,null]);
        }
        $params = $request->validate([
            'email' => 'required|email:strict',
            'redirect' => 'nullable'
        ]);

        if (Cache::get(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $params['email']))) {
            return $this->fail([429 ,__('Sending frequently, please try again later')]);
        }

        $user = User::where('email', $params['email'])->first();
        if (!$user) {
            return $this->success(true);
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user->id, 300);
        Cache::put(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $params['email']), time(), 60);


        $redirect = '/#/login?verify=' . $code . '&redirect=' . ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
        if (admin_setting('app_url')) {
            $link = admin_setting('app_url') . $redirect;
        } else {
            $link = url($redirect);
        }

        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('Login to :name', [
                'name' => admin_setting('app_name', 'XBoard')
            ]),
            'template_name' => 'login',
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'link' => $link,
                'url' => admin_setting('app_url')
            ]
        ]);

        return $this->success($link);

    }

    public function register(AuthRegister $request)
    {
        if ((int)admin_setting('register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            if ((int)$registerCountByIP >= (int)admin_setting('register_limit_count', 3)) {
                return $this->fail([429,__('Register frequently, please try again after :minute minute', [
                    'minute' => admin_setting('register_limit_expire', 60)
                ])]);
            }
        }
        if ((int)admin_setting('recaptcha_enable', 0)) {
            $recaptcha = new ReCaptcha(admin_setting('recaptcha_key'));
            $recaptchaResp = $recaptcha->verify($request->input('recaptcha_data'));
            if (!$recaptchaResp->isSuccess()) {
                return $this->fail([400,__('Invalid code is incorrect')]);
            }
        }
        if ((int)admin_setting('email_whitelist_enable', 0)) {
            if (!Helper::emailSuffixVerify(
                $request->input('email'),
                admin_setting('email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT))
            ) {
                return $this->fail([400,__('Email suffix is not in the Whitelist')]);
            }
        }
        if ((int)admin_setting('email_gmail_limit_enable', 0)) {
            $prefix = explode('@', $request->input('email'))[0];
            if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
                return $this->fail([400,__('Gmail alias is not supported')]);
            }
        }
        if ((int)admin_setting('stop_register', 0)) {
            return $this->fail([400,__('Registration has closed')]);
        }
        if ((int)admin_setting('invite_force', 0)) {
            if (empty($request->input('invite_code'))) {
                return $this->fail([422,__('You must use the invitation code to register')]);
            }
        }
        if ((int)admin_setting('email_verify', 0)) {
            if (empty($request->input('email_code'))) {
                return $this->fail([422,__('Email verification code cannot be empty')]);
            }
            if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
                return $this->fail([400,__('Incorrect email verification code')]);
            }
        }
        $email = $request->input('email');
        $password = $request->input('password');
        $exist = User::where('email', $email)->first();
        if ($exist) {
            return $this->fail([400201,__('Email already exists')]);
        }
        $user = new User();
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        // TODO 增加过期默认值、流量告急提醒默认值 
        $user->remind_expire = admin_setting('default_remind_expire',1);
        $user->remind_traffic = admin_setting('default_remind_traffic',1);
        if ($request->input('invite_code')) {
            $inviteCode = InviteCode::where('code', $request->input('invite_code'))
                ->where('status', 0)
                ->first();
            if (!$inviteCode) {
                if ((int)admin_setting('invite_force', 0)) {
                    return $this->fail([400,__('Invalid invitation code')]);
                }
            } else {
                $user->invite_user_id = $inviteCode->user_id ? $inviteCode->user_id : null;
                if (!(int)admin_setting('invite_never_expire', 0)) {
                    $inviteCode->status = 1;
                    $inviteCode->save();
                }
            }
        }

        // try out
        if ((int)admin_setting('try_out_plan_id', 0)) {
            $plan = Plan::find(admin_setting('try_out_plan_id'));
            if ($plan) {
                $user->transfer_enable = $plan->transfer_enable * 1073741824;
                $user->plan_id = $plan->id;
                $user->group_id = $plan->group_id;
                $user->expired_at = time() + (admin_setting('try_out_hour', 1) * 3600);
                $user->speed_limit = $plan->speed_limit;
            }
        }

        if (!$user->save()) {
            return $this->fail([500,__('Register failed')]);
        }
        if ((int)admin_setting('email_verify', 0)) {
            Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        }

        $user->last_login_at = time();
        $user->save();

        if ((int)admin_setting('register_limit_by_ip_enable', 0)) {
            Cache::put(
                CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip()),
                (int)$registerCountByIP + 1,
                (int)admin_setting('register_limit_expire', 60) * 60
            );
        }

        $authService = new AuthService($user);

        $data = $authService->generateAuthData($request);
        return $this->success($data);
    }

    public function login(AuthLogin $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if ((int)admin_setting('password_limit_enable', 1)) {
            $passwordErrorCount = (int)Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
            if ($passwordErrorCount >= (int)admin_setting('password_limit_count', 5)) {
                return $this->fail([429,__('There are too many password errors, please try again after :minute minutes.', [
                    'minute' => admin_setting('password_limit_expire', 60)
                ])]);
            }
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return $this->fail([400, __('Incorrect email or password')]);
        }
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $password,
            $user->password)
        ) {
            if ((int)admin_setting('password_limit_enable')) {
                Cache::put(
                    CacheKey::get('PASSWORD_ERROR_LIMIT', $email),
                    (int)$passwordErrorCount + 1,
                    60 * (int)admin_setting('password_limit_expire', 60)
                );
            }
            return $this->fail([400, __('Incorrect email or password')]);
        }

        if ($user->banned) {
            return $this->fail([400, __('Your account has been suspended')]);
        }

        $authService = new AuthService($user);
        return $this->success($authService->generateAuthData($request));
    }

    public function token2Login(Request $request)
    {
        if ($request->input('token')) {
            $redirect = '/#/login?verify=' . $request->input('token') . '&redirect=' . ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
            if (admin_setting('app_url')) {
                $location = admin_setting('app_url') . $redirect;
            } else {
                $location = url($redirect);
            }
            return redirect()->to($location)->send();
        }

        if ($request->input('verify')) {
            $key =  CacheKey::get('TEMP_TOKEN', $request->input('verify'));
            $userId = Cache::get($key);
            if (!$userId) {
                return $this->fail([400,__('Token error')]);
            }
            $user = User::find($userId);
            if (!$user) {
                return $this->fail([400,__('The user does not ')]);
            }
            if ($user->banned) {
                return $this->fail([400,__('Your account has been suspended')]);
            }
            Cache::forget($key);
            $authService = new AuthService($user);
            return $this->success($authService->generateAuthData($request));
        }
    }

    public function getQuickLoginUrl(Request $request)
    {
        $authorization = $request->input('auth_data') ?? $request->header('authorization');
        if (!$authorization) return $this->fail(ResponseEnum::CLIENT_HTTP_UNAUTHORIZED);

        $user = AuthService::decryptAuthData($authorization);
        if (!$user) return $this->fail(ResponseEnum::CLIENT_HTTP_UNAUTHORIZED_EXPIRED);

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user['id'], 60);
        $redirect = '/#/login?verify=' . $code . '&redirect=' . ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
        if (admin_setting('app_url')) {
            $url = admin_setting('app_url') . $redirect;
        } else {
            $url = url($redirect);
        }
        return $this->success($url);
    }

    public function forget(AuthForget $request)
    {
        $forgetRequestLimitKey = CacheKey::get('FORGET_REQUEST_LIMIT', $request->input('email'));
        $forgetRequestLimit = (int)Cache::get($forgetRequestLimitKey);
        if ($forgetRequestLimit >= 3) return $this->fail([429, __('Reset failed, Please try again later')]);
        if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
            Cache::put($forgetRequestLimitKey, $forgetRequestLimit ? $forgetRequestLimit + 1 : 1, 300);
            return $this->fail([400,__('Incorrect email verification code')]);
        }
        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return $this->fail([400,__('This email is not registered in the system')]);
        }
        $user->password = password_hash($request->input('password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        $user->password_salt = NULL;
        if (!$user->save()) {
            return $this->fail([500,__('Reset failed')]);
        }
        Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        return $this->success(true);
    }
}
