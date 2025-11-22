<?php


namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Server as ServerModel;
use App\Services\ServerService;
use Closure;
use Illuminate\Http\Request;

class Server
{
    public function handle(Request $request, Closure $next, ?string $nodeType = null)
    {
        $this->validateRequest($request);
        $nodeType = $request->input('node_type', $nodeType);
        $normalizedNodeType = ServerModel::normalizeType($nodeType);
        $serverInfo = ServerService::getServer(
            $request->input('node_id'),
            $normalizedNodeType
        );
        if (!$serverInfo) {
            throw new ApiException('Server does not exist');
        }

        $request->attributes->set('node_info', $serverInfo);
        return $next($request);
    }

    private function validateRequest(Request $request): void
    {
        $request->validate([
            'token' => [
                'string',
                'required',
                function ($attribute, $value, $fail) {
                    if ($value !== admin_setting('server_token')) {
                        $fail("Invalid {$attribute}");
                    }
                },
            ],
            'node_id' => 'required',
            'node_type' => [
                'nullable',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value === "v2node") {
                        $value = null;
                    }
                    if (!ServerModel::isValidType($value)) {
                        $fail("Invalid node type specified");
                        return;
                    }
                    $request->merge([$attribute => ServerModel::normalizeType($value)]);
                },
            ]
        ]);
    }
}
