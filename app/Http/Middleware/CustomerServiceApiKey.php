<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CustomerServiceApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expectedKey = admin_setting('customer_service_api_key', env('CUSTOMER_SERVICE_API_KEY'));

        if (!$expectedKey) {
            return response()->json([
                'message' => 'Customer service API key is not configured'
            ], 403);
        }

        $providedKey = $request->header('X-Customer-Service-Key');

        if (!$providedKey || !hash_equals((string) $expectedKey, (string) $providedKey)) {
            return response()->json([
                'message' => 'Invalid customer service API key'
            ], 401);
        }

        return $next($request);
    }
}
