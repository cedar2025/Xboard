<?php

namespace App\Services\Auth;

use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Facades\Cache;

class MailLinkService
{
    /**
     * 处理邮件链接登录逻辑
     *
     * @param string $email 用户邮箱
     * @param string|null $redirect 重定向地址
     * @return array 返回处理结果
     */
    public function handleMailLink(string $email, ?string $redirect = null): array
    {
        if (!(int)admin_setting('login_with_mail_link_enable')) {
            return [false, [404, null]];
        }

        if (Cache::get(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $email))) {
            return [false, [429, __('Sending frequently, please try again later')]];
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return [true, true]; // 成功但用户不存在，保护用户隐私
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user->id, 300);
        Cache::put(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $email), time(), 60);

        $redirectUrl = '/#/login?verify=' . $code . '&redirect=' . ($redirect ? $redirect : 'dashboard');
        if (admin_setting('app_url')) {
            $link = admin_setting('app_url') . $redirectUrl;
        } else {
            $link = url($redirectUrl);
        }

        $this->sendMailLinkEmail($user, $link);

        return [true, $link];
    }

    /**
     * 发送邮件链接登录邮件
     *
     * @param User $user 用户对象
     * @param string $link 登录链接
     * @return void
     */
    private function sendMailLinkEmail(User $user, string $link): void
    {
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
    }

    /**
     * 获取快速登录URL
     * 
     * @param User $user 用户对象
     * @param string|null $redirect 重定向地址
     * @return string 登录URL
     */
    public function getQuickLoginUrl(User $user, ?string $redirect = null): string
    {
        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user->id, 60);
        
        $redirectUrl = '/#/login?verify=' . $code . '&redirect=' . ($redirect ? $redirect : 'dashboard');
        
        if (admin_setting('app_url')) {
            return admin_setting('app_url') . $redirectUrl;
        } else {
            return url($redirectUrl);
        }
    }

    /**
     * 处理Token登录
     * 
     * @param string $token 登录令牌
     * @return int|null 用户ID或null
     */
    public function handleTokenLogin(string $token): ?int
    {
        $key = CacheKey::get('TEMP_TOKEN', $token);
        $userId = Cache::get($key);
        
        if (!$userId) {
            return null;
        }
        
        $user = User::find($userId);
        
        if (!$user || $user->banned) {
            return null;
        }
        
        Cache::forget($key);
        
        return $userId;
    }
} 