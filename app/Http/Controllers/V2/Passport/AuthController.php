<?php

namespace App\Http\Controllers\V2\Passport;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailJob;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    /**
     * Send login link to email
     *
     * @param Request $request
     * @return JsonResponse
     * @api POST /api/v2/auth/magicLogin
     */
    public function magicLogin(Request $request)
    {
        $params = $request->validate([
            'email' => 'required|email:strict',
            'redirect' => 'nullable'
        ]);

        if (Cache::get(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $params['email']))) {
            return $this->fail([429, __('Sending frequently, please try again later')]);
        }
        Cache::put(CacheKey::get('LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP', $params['email']), time(), 60); // 1 minute

        $userService = new UserService();
        $user = $userService->getUsersByEmail($params['email'])->first();
        if (!$user) {
            return $this->success(__('If the email exists, a login link will be sent to the email'));
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);
        Cache::put($key, $user->id, 7 * 24 * 3600); // 7 days

        $redirect = $request->input('redirect') ? $request->input('redirect') : 'dashboard';
        $loginUrl = '/#/login?verify=' . $code . '&redirect=' . $redirect;
        if (admin_setting('app_url')) {
            $loginUrl = admin_setting('app_url') . $loginUrl;
        } else {
            $loginUrl = url($loginUrl);
        }

        // Send email
        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('Login to :name', [
                'name' => admin_setting('app_name', 'XBoard')
            ]),
            'template_name' => 'mailLogin',
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'link' => $loginUrl,
                'url' => admin_setting('app_url')
            ]
        ]);

        return $this->success(__('If the email exists, a login link will be sent to the email'));
    }
}
