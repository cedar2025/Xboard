<?php

namespace App\Http\Middleware;

use Closure;

class RequestLog
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
        $method = $request->method();
        $path = $request->path();
        $ip = $request->getClientIp();
        $userAgent = $request->header('User-Agent');
        
        // 记录请求基本信息
        info("HTTP Request: {$method} {$path}", [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'body' => $request->all()
        ]);
        
        return $next($request);
    }
}
