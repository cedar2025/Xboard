<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\AuthService;
use Closure;

class Staff
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $authorization = $request->input('auth_data') ?? $request->header('authorization');
        if (!$authorization) throw new ApiException( '未登录或登陆已过期', 403);

        $user = AuthService::decryptAuthData($authorization);
        if (!$user || !$user['is_staff']) throw new ApiException('未登录或登陆已过期', 403);
        $request->merge([
            'user' => $user
        ]);
        return $next($request);
    }
}
