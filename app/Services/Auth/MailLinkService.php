<?php

namespace App\Services\Auth;

use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

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
        if (!(int) admin_setting('login_with_mail_link_enable')) {
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
     * 处理Token登录
     * 
     * @param string $token 登录令牌
     * @param Request|null $request 请求对象（用于获取IP地址）
     * @return int|null 用户ID或null
     */
    public function handleTokenLogin(string $token, ?Request $request = null): ?int
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
        
        // Update last login time and IP
        $user->last_login_at = time();
        if ($request) {
            $clientIp = $this->getRealClientIp($request);
            $user->last_login_ip = $clientIp;
            $user->save();
        }
        
        Cache::forget($key);

        return $userId;
    }

    /**
     * 获取真实客户端IP地址（优先使用Cloudflare头）
     * 
     * @param Request $request
     * @return string
     */
    private function getRealClientIp(Request $request): string
    {
        // 1. 优先使用Cloudflare的CF-Connecting-IP头
        if ($request->hasHeader('CF-Connecting-IP')) {
            $cfIp = $request->header('CF-Connecting-IP');
            if ($this->isValidPublicIp($cfIp)) {
                return $cfIp;
            }
        }
        
        // 2. 尝试X-Real-IP头
        if ($request->hasHeader('X-Real-IP')) {
            $realIp = $request->header('X-Real-IP');
            if ($this->isValidPublicIp($realIp)) {
                return $realIp;
            }
        }
        
        // 3. 尝试X-Forwarded-For头（取第一个公网IP）
        if ($request->hasHeader('X-Forwarded-For')) {
            $forwardedFor = $request->header('X-Forwarded-For');
            $ips = array_map('trim', explode(',', $forwardedFor));
            foreach ($ips as $ip) {
                if ($this->isValidPublicIp($ip)) {
                    return $ip;
                }
            }
        }
        
        // 4. 最后使用Laravel的ip()方法作为备选
        return $request->ip();
    }

    /**
     * 验证是否为有效的公网IP地址
     */
    private function isValidPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
