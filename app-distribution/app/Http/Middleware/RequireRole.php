<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $admin = $request->user();
        if (!$admin || !$admin->hasAnyRole($roles)) {
            abort(403, 'Permission denied');
        }

        return $next($request);
    }
}
