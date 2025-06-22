<?php

namespace App\Http\Controllers\V1\Passport;

use App\Helpers\ResponseEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passport\AuthForget;
use App\Http\Requests\Passport\AuthLogin;
use App\Http\Requests\Passport\AuthRegister;
use App\Services\Auth\LoginService;
use App\Services\Auth\MailLinkService;
use App\Services\Auth\RegisterService;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected MailLinkService $mailLinkService;
    protected RegisterService $registerService;
    protected LoginService $loginService;

    public function __construct(
        MailLinkService $mailLinkService,
        RegisterService $registerService,
        LoginService $loginService
    ) {
        $this->mailLinkService = $mailLinkService;
        $this->registerService = $registerService;
        $this->loginService = $loginService;
    }

    /**
     * 通过邮件链接登录
     */
    public function loginWithMailLink(Request $request)
    {
        $params = $request->validate([
            'email' => 'required|email:strict',
            'redirect' => 'nullable'
        ]);

        // Convert email to lowercase for consistent handling
        $params['email'] = strtolower($params['email']);

        [$success, $result] = $this->mailLinkService->handleMailLink(
            $params['email'], 
            $request->input('redirect')
        );

        if (!$success) {
            return $this->fail($result);
        }

        return $this->success($result);
    }

    /**
     * 用户注册
     */
    public function register(AuthRegister $request)
    {
        // Convert email to lowercase for consistent handling
        $request->merge(['email' => strtolower($request->input('email'))]);
        
        [$success, $result] = $this->registerService->register($request);

        if (!$success) {
            return $this->fail($result);
        }

        $authService = new AuthService($result);
        return $this->success($authService->generateAuthData());
    }

    /**
     * 用户登录
     */
    public function login(AuthLogin $request)
    {
        // Convert email to lowercase for consistent handling
        $email = strtolower($request->input('email'));
        $password = $request->input('password');

        [$success, $result] = $this->loginService->login($email, $password, $request);

        if (!$success) {
            return $this->fail($result);
        }

        $authService = new AuthService($result);
        return $this->success($authService->generateAuthData());
    }

    /**
     * 通过token登录
     */
    public function token2Login(Request $request)
    {
        // 处理直接通过token重定向
        if ($token = $request->input('token')) {
            $redirect = '/#/login?verify=' . $token . '&redirect=' . ($request->input('redirect', 'dashboard'));
            
            return redirect()->to(
                admin_setting('app_url')
                    ? admin_setting('app_url') . $redirect
                    : url($redirect)
            );
        }

        // 处理通过验证码登录
        if ($verify = $request->input('verify')) {
            $userId = $this->mailLinkService->handleTokenLogin($verify, $request);
            
            if (!$userId) {
                return response()->json([
                    'message' => __('Token error')
                ], 400);
            }
            
            $user = \App\Models\User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'message' => __('User not found')
                ], 400);
            }
            
            $authService = new AuthService($user);
            
            return response()->json([
                'data' => $authService->generateAuthData()
            ]);
        }
        
        return response()->json([
            'message' => __('Invalid request')
        ], 400);
    }

    /**
     * 获取快速登录URL
     */
    public function getQuickLoginUrl(Request $request)
    {
        $authorization = $request->input('auth_data') ?? $request->header('authorization');
        
        if (!$authorization) {
            return response()->json([
                'message' => ResponseEnum::CLIENT_HTTP_UNAUTHORIZED
            ], 401);
        }

        $user = AuthService::findUserByBearerToken($authorization);
        
        if (!$user) {
            return response()->json([
                'message' => ResponseEnum::CLIENT_HTTP_UNAUTHORIZED_EXPIRED
            ], 401);
        }
        
        $url = $this->mailLinkService->getQuickLoginUrl($user, $request->input('redirect'));
        return $this->success($url);
    }

    /**
     * 忘记密码处理
     */
    public function forget(AuthForget $request)
    {
        [$success, $result] = $this->loginService->resetPassword(
            $request->input('email'),
            $request->input('email_code'),
            $request->input('password')
        );

        if (!$success) {
            return $this->fail($result);
        }
        return $this->success(true);
    }

    /**
     * 将IP地址转换为整数存储
     * 
     * @param string $ip IP地址
     * @return int 转换后的整数值
     */
    private function ipToInt(string $ip): int
    {
        // 处理IPv4地址
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            // 处理负数情况（32位系统）
            return $long < 0 ? $long + 4294967296 : $long;
        }
        
        // 对于IPv6或其他情况，使用CRC32哈希作为备选方案
        return abs(crc32($ip));
    }
}
