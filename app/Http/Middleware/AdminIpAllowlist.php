<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class AdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = trim((string) config('admin.ip_allowlist', ''));
        if ($allowlist === '') {
            return $next($request);
        }

        $entries = array_filter(array_map('trim', explode(',', $allowlist)));
        if (IpUtils::checkIp((string) $request->ip(), array_values($entries))) {
            return $next($request);
        }

        abort(403, 'Admin access from this IP is not allowed.');
    }
}
