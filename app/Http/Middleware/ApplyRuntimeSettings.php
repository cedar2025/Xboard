<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ApplyRuntimeSettings
{
    public function handle(Request $request, Closure $next)
    {
        $appUrl = admin_setting('app_url');
        if (is_string($appUrl) && $appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }

        if ((bool) admin_setting('force_https', false)) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}

