<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Facades\Cache;

class LoginService
{
    /**
     * 处理用户登录
     *
     * @param string $email 用户邮箱
     * @param string $password 用户密码
     * @return array [成功状态, 用户对象或错误信息]
     */
    public function login(string $email, string $password): array
    {
        // 检查密码错误限制
        if ((int)admin_setting('password_limit_enable', true)) {
            $passwordErrorCount = (int)Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
            if ($passwordErrorCount >= (int)admin_setting('password_limit_count', 5)) {
                return [false, [429, __('There are too many password errors, please try again after :minute minutes.', [
                    'minute' => admin_setting('password_limit_expire', 60)
                ])]];
            }
        }

        // 查找用户
        $user = User::where('email', $email)->first();
        if (!$user) {
            return [false, [400, __('Incorrect email or password')]];
        }

        // 验证密码
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $password,
            $user->password)
        ) {
            // 增加密码错误计数
            if ((int)admin_setting('password_limit_enable', true)) {
                $passwordErrorCount = (int)Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
                Cache::put(
                    CacheKey::get('PASSWORD_ERROR_LIMIT', $email),
                    (int)$passwordErrorCount + 1,
                    60 * (int)admin_setting('password_limit_expire', 60)
                );
            }
            return [false, [400, __('Incorrect email or password')]];
        }

        // 检查账户状态
        if ($user->banned) {
            return [false, [400, __('Your account has been suspended')]];
        }

        // 更新最后登录时间
        $user->last_login_at = time();
        $user->save();

        return [true, $user];
    }

    /**
     * 处理密码重置
     *
     * @param string $email 用户邮箱
     * @param string $emailCode 邮箱验证码
     * @param string $password 新密码
     * @return array [成功状态, 结果或错误信息]
     */
    public function resetPassword(string $email, string $emailCode, string $password): array
    {
        // 检查重置请求限制
        $forgetRequestLimitKey = CacheKey::get('FORGET_REQUEST_LIMIT', $email);
        $forgetRequestLimit = (int)Cache::get($forgetRequestLimitKey);
        if ($forgetRequestLimit >= 3) {
            return [false, [429, __('Reset failed, Please try again later')]];
        }

        // 验证邮箱验证码
        if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $email)) !== (string)$emailCode) {
            Cache::put($forgetRequestLimitKey, $forgetRequestLimit ? $forgetRequestLimit + 1 : 1, 300);
            return [false, [400, __('Incorrect email verification code')]];
        }

        // 查找用户
        $user = User::where('email', $email)->first();
        if (!$user) {
            return [false, [400, __('This email is not registered in the system')]];
        }

        // 更新密码
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        $user->password_salt = NULL;
        
        if (!$user->save()) {
            return [false, [500, __('Reset failed')]];
        }

        // 清除邮箱验证码
        Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $email));
        
        return [true, true];
    }
} 