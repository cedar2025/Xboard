<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Facades\Auth;

class Client
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
        $routeToken = $request->route('token');
        $token = $routeToken !== null ? $routeToken : $request->query('token');

        if ($token === null || $token === '') {
            throw new ApiException('token is null', 403);
        }
        $token = Helper::validateSubscriptionToken($token);
        if ($token === null) {
            throw new ApiException('token is error', 403);
        }

        $user = User::where('token', $token)->first();
        if (!$user) {
            throw new ApiException('token is error', 403);
        }

        Auth::setUser($user);
        return $next($request);
    }
}
