<?php

namespace App\Services\Auth;

use App\Models\InviteCode;
use App\Models\Plan;
use App\Models\User;
use App\Utils\CacheKey;
use App\Utils\Dict;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ReCaptcha\ReCaptcha;

class RegisterService
{
    /**
     * 验证用户注册请求
     *
     * @param Request $request 请求对象
     * @return array [是否通过, 错误消息]
     */
    public function validateRegister(Request $request): array
    {
        // 检查IP注册限制
        if ((int)admin_setting('register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            if ((int)$registerCountByIP >= (int)admin_setting('register_limit_count', 3)) {
                return [false, [429, __('Register frequently, please try again after :minute minute', [
                    'minute' => admin_setting('register_limit_expire', 60)
                ])]];
            }
        }

        // 检查验证码
        if ((int)admin_setting('recaptcha_enable', 0)) {
            $recaptcha = new ReCaptcha(admin_setting('recaptcha_key'));
            $recaptchaResp = $recaptcha->verify($request->input('recaptcha_data'));
            if (!$recaptchaResp->isSuccess()) {
                return [false, [400, __('Invalid code is incorrect')]];
            }
        }

        // 检查邮箱白名单
        if ((int)admin_setting('email_whitelist_enable', 0)) {
            if (!Helper::emailSuffixVerify(
                $request->input('email'),
                admin_setting('email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT))
            ) {
                return [false, [400, __('Email suffix is not in the Whitelist')]];
            }
        }

        // 检查Gmail限制
        if ((int)admin_setting('email_gmail_limit_enable', 0)) {
            $prefix = explode('@', $request->input('email'))[0];
            if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
                return [false, [400, __('Gmail alias is not supported')]];
            }
        }

        // 检查是否关闭注册
        if ((int)admin_setting('stop_register', 0)) {
            return [false, [400, __('Registration has closed')]];
        }

        // 检查邀请码要求
        if ((int)admin_setting('invite_force', 0)) {
            if (empty($request->input('invite_code'))) {
                return [false, [422, __('You must use the invitation code to register')]];
            }
        }

        // 检查邮箱验证
        if ((int)admin_setting('email_verify', 0)) {
            if (empty($request->input('email_code'))) {
                return [false, [422, __('Email verification code cannot be empty')]];
            }
            if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
                return [false, [400, __('Incorrect email verification code')]];
            }
        }

        // 检查邮箱是否存在
        $email = $request->input('email');
        $exist = User::where('email', $email)->first();
        if ($exist) {
            return [false, [400201, __('Email already exists')]];
        }

        return [true, null];
    }

    /**
     * 处理邀请码
     *
     * @param User $user 用户对象
     * @param string|null $inviteCode 邀请码
     * @return array [是否成功, 错误消息]
     */
    public function handleInviteCode(User $user, ?string $inviteCode): array
    {
        if (!$inviteCode) {
            return [true, null];
        }

        $inviteCodeModel = InviteCode::where('code', $inviteCode)
            ->where('status', 0)
            ->first();

        if (!$inviteCodeModel) {
            if ((int)admin_setting('invite_force', 0)) {
                return [false, [400, __('Invalid invitation code')]];
            }
            return [true, null];
        }

        $user->invite_user_id = $inviteCodeModel->user_id ? $inviteCodeModel->user_id : null;
        
        if (!(int)admin_setting('invite_never_expire', 0)) {
            $inviteCodeModel->status = true;
            $inviteCodeModel->save();
        }

        return [true, null];
    }

    /**
     * 处理试用计划
     *
     * @param User $user 用户对象
     * @return void
     */
    public function handleTryOut(User $user): void
    {
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
    }

    /**
     * 注册用户
     *
     * @param Request $request 请求对象
     * @return array [成功状态, 用户对象或错误信息]
     */
    public function register(Request $request): array
    {
        // 验证注册数据
        [$valid, $error] = $this->validateRegister($request);
        if (!$valid) {
            return [false, $error];
        }

        $email = $request->input('email');
        $password = $request->input('password');

        // 创建用户
        $user = new User();
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->remind_expire = admin_setting('default_remind_expire', 1);
        $user->remind_traffic = admin_setting('default_remind_traffic', 1);

        // 处理邀请码
        [$inviteSuccess, $inviteError] = $this->handleInviteCode($user, $request->input('invite_code'));
        if (!$inviteSuccess) {
            return [false, $inviteError];
        }

        // 处理试用计划
        $this->handleTryOut($user);

        // 保存用户
        if (!$user->save()) {
            return [false, [500, __('Register failed')]];
        }

        // 清除邮箱验证码
        if ((int)admin_setting('email_verify', 0)) {
            Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $email));
        }

        // 更新最近登录时间
        $user->last_login_at = time();
        $user->save();

        // 更新IP注册计数
        if ((int)admin_setting('register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            Cache::put(
                CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip()),
                (int)$registerCountByIP + 1,
                (int)admin_setting('register_limit_expire', 60) * 60
            );
        }

        return [true, $user];
    }
} 