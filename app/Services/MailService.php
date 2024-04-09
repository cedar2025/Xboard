<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class MailService
{
    public function remindTraffic(User $user)
    {
        if (!$user->remind_traffic)
            return;
        if (!$this->remindTrafficIsWarnValue($user->u, $user->d, $user->transfer_enable))
            return;
        $flag = CacheKey::get('LAST_SEND_EMAIL_REMIND_TRAFFIC', $user->id);
        if (Cache::get($flag))
            return;
        if (!Cache::put($flag, 1, 24 * 3600))
            return;
        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('The traffic usage in :app_name has reached 80%', [
                'app_name' => admin_setting('app_name', 'XBoard')
            ]),
            'template_name' => 'remindTraffic',
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'url' => admin_setting('app_url')
            ]
        ]);
    }

    public function remindExpire(User $user)
    {
        if (!($user->expired_at !== NULL && ($user->expired_at - 86400) < time() && $user->expired_at > time()))
            return;
        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('The service in :app_name is about to expire', [
                'app_name' => admin_setting('app_name', 'XBoard')
            ]),
            'template_name' => 'remindExpire',
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'url' => admin_setting('app_url')
            ]
        ]);
    }

    private function remindTrafficIsWarnValue($u, $d, $transfer_enable)
    {
        $ud = $u + $d;
        if (!$ud)
            return false;
        if (!$transfer_enable)
            return false;
        $percentage = ($ud / $transfer_enable) * 100;
        if ($percentage < 80)
            return false;
        if ($percentage >= 100)
            return false;
        return true;
    }

    /**
     * 发送邮件
     *
     * @param array $params 包含邮件参数的数组，必须包含以下字段：
     *   - email: 收件人邮箱地址
     *   - subject: 邮件主题
     *   - template_name: 邮件模板名称，例如 "welcome" 或 "password_reset"
     *   - template_value: 邮件模板变量，一个关联数组，包含模板中需要替换的变量和对应的值
     * @return array 包含邮件发送结果的数组，包含以下字段：
     *   - email: 收件人邮箱地址
     *   - subject: 邮件主题
     *   - template_name: 邮件模板名称
     *   - error: 如果邮件发送失败，包含错误信息；否则为 null
     * @throws \InvalidArgumentException 如果 $params 参数缺少必要的字段，抛出此异常
     */
    public static function sendEmail(array $params)
    {
        if (admin_setting('email_host')) {
            \Config::set('mail.host', admin_setting('email_host', config('mail.host')));
            \Config::set('mail.port', admin_setting('email_port', config('mail.port')));
            \Config::set('mail.encryption', admin_setting('email_encryption', config('mail.encryption')));
            \Config::set('mail.username', admin_setting('email_username', config('mail.username')));
            \Config::set('mail.password', admin_setting('email_password', config('mail.password')));
            \Config::set('mail.from.address', admin_setting('email_from_address', config('mail.from.address')));
            \Config::set('mail.from.name', admin_setting('app_name', 'XBoard'));
        }
        $email = $params['email'];
        $subject = $params['subject'];
        $params['template_name'] = 'mail.' . admin_setting('email_template', 'default') . '.' . $params['template_name'];
        try {
            \Mail::send(
                $params['template_name'],
                $params['template_value'],
                function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                }
            );
            $error = null;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        $log = [
            'email' => $params['email'],
            'subject' => $params['subject'],
            'template_name' => $params['template_name'],
            'error' => $error,
            'config' => config('mail')
        ];
        \App\Models\MailLog::create($log);
        return $log;
    }
}
