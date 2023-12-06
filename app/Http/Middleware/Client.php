<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Utils\CacheKey;
use Closure;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

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
        $token = $request->input('token');
        if (empty($token)) {
            throw new ApiException('token is null',403);
        }
        $user = User::where('token', $token)->first();
        if (!$user) {
            throw new ApiException('token is error',403);
        }
        $request->merge([
            'user' => $user
        ]);
        return $next($request);
    }
}
