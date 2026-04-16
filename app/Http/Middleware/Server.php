<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Server as ServerModel;
use App\Models\ServerMachine;
use App\Services\ServerService;
use Closure;
use Illuminate\Http\Request;

class Server
{
    public function handle(Request $request, Closure $next, ?string $nodeType = null)
    {
        // 优先尝试 machine token 认证，兜底走旧的 server token 认证
        if ($request->filled('machine_id')) {
            $this->authenticateByMachine($request, $nodeType);
        } else {
            $this->authenticateByServerToken($request, $nodeType);
        }

        return $next($request);
    }

    /**
     * 旧模式：全局 server_token + node_id
     */
    private function authenticateByServerToken(Request $request, ?string $nodeType): void
    {
        $request->validate([
            'token' => [
                'string', 'required',
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
                    if ($value === 'v2node') {
                        $value = null;
                    }
                    if (!ServerModel::isValidType($value)) {
                        $fail('Invalid node type specified');
                        return;
                    }
                    $request->merge([$attribute => ServerModel::normalizeType($value)]);
                },
            ]
        ]);

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
    }

    /**
     * 新模式：machine_id + machine token + node_id
     *
     * machine 认证后，node_id 必须属于该 machine 下的已启用节点。
     * 下游控制器拿到的 node_info 与旧模式完全一致。
     */
    private function authenticateByMachine(Request $request, ?string $nodeType): void
    {
        $isHandshake = $request->is('*/server/handshake') || $request->is('api/v2/server/handshake');

        $request->validate([
            'machine_id' => 'required|integer',
            'token' => 'required|string',
            'node_id' => $isHandshake ? 'nullable|integer' : 'required|integer',
        ]);

        $machine = ServerMachine::where('id', $request->input('machine_id'))
            ->where('token', $request->input('token'))
            ->first();

        if (!$machine) {
            throw new ApiException('Machine not found or invalid token', 401);
        }

        if (!$machine->is_active) {
            throw new ApiException('Machine is disabled', 403);
        }

        $nodeId = (int) $request->input('node_id');
        $serverInfo = null;

        if ($nodeId > 0) {
            $serverInfo = ServerModel::where('id', $nodeId)
                ->where('machine_id', $machine->id)
                ->where('enabled', true)
                ->first();

            if (!$serverInfo) {
                throw new ApiException('Node not found on this machine');
            }

            $request->attributes->set('node_info', $serverInfo);
        }

        // 更新机器心跳
        $machine->forceFill(['last_seen_at' => now()->timestamp])->saveQuietly();

        $request->attributes->set('machine_info', $machine);
    }
}
