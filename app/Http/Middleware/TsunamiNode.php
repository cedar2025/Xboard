<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Server;
use App\Models\TsunamiCredential;
use Closure;
use Illuminate\Http\Request;

class TsunamiNode
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            throw new ApiException('Missing TSUNAMI node credential', 401);
        }

        $hash = hash('sha256', $token);
        $credential = TsunamiCredential::query()
            ->with('server')
            ->where('credential_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (!$credential || !hash_equals((string) $credential->credential_hash, $hash)) {
            throw new ApiException('Invalid TSUNAMI node credential', 401);
        }

        $server = $credential->server;
        if (!$server || $server->type !== Server::TYPE_TSUNAMI) {
            throw new ApiException('TSUNAMI node does not exist', 401);
        }
        if ($server->enabled === false) {
            throw new ApiException('TSUNAMI node is disabled', 403);
        }

        $request->attributes->set('node_info', $server);
        $request->attributes->set('tsunami_credential', $credential);

        return $next($request);
    }
}
