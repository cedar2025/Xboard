<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureTransactionState
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            // Rollback any stale transactions to ensure a clean state for the next request.
            // This is crucial for long-running processes like Octane.
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
