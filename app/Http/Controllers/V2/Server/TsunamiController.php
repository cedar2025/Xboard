<?php

namespace App\Http\Controllers\V2\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\TsunamiCredential;
use App\Models\User;
use App\Services\ServerService;
use App\Services\TsunamiDeviceLeaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TsunamiController extends Controller
{
    public function __construct(private readonly TsunamiDeviceLeaseService $deviceLeases)
    {
    }

    public function enroll(Request $request): JsonResponse
    {
        $params = $request->validate([
            'node_id' => 'required|string|max:64',
            'enrollment_token' => 'required|string|min:32|max:512',
        ]);

        $node = ServerService::getServer($params['node_id'], Server::TYPE_TSUNAMI);
        if (!$node || $node->enabled === false) {
            throw new ApiException('TSUNAMI node does not exist or is disabled', 401);
        }

        $hash = hash('sha256', $params['enrollment_token']);
        $nodeCredential = DB::transaction(function () use ($node, $hash): string {
            // Lock the enrollment row so a one-time token cannot be exchanged
            // by two concurrent installers before either request clears it.
            $credential = TsunamiCredential::where('server_id', $node->id)
                ->lockForUpdate()
                ->first();
            if (!$credential
                || !$credential->enrollment_hash
                || !$credential->enrollment_expires_at
                || $credential->enrollment_expires_at->isPast()
                || !hash_equals($credential->enrollment_hash, $hash)) {
                throw new ApiException('Invalid or expired enrollment token', 401);
            }

            $nodeCredential = bin2hex(random_bytes(32));
            $credential->forceFill([
                'credential_hash' => hash('sha256', $nodeCredential),
                'enrollment_hash' => null,
                'enrollment_expires_at' => null,
                'enrolled_at' => now(),
                'revoked_at' => null,
            ])->save();

            return $nodeCredential;
        });

        return response()->json([
            // ServerService accepts XBoard's code or database id. Preserve the
            // bootstrap identifier so aliases do not make the node reject a
            // valid enrollment response.
            'node_id' => (string) $params['node_id'],
            'credential' => $nodeCredential,
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        $node = $this->node($request);
        ServerService::touchNode($node);

        $config = ServerService::buildNodeConfig($node);
        $revision = hash('sha256', json_encode($config, JSON_UNESCAPED_SLASHES));
        if ($this->matchesEtag($request, $revision)) {
            return response()->json(null, 304);
        }

        return response()
            ->json(['revision' => $revision, 'config' => $config])
            ->header('ETag', "\"{$revision}\"");
    }

    public function users(Request $request): JsonResponse
    {
        $node = $this->node($request);
        ServerService::touchNode($node);

        $users = ServerService::getAvailableUsers($node)
            ->map(fn ($user) => [
                'id' => (string) $user->id,
                'uuid' => $user->uuid,
                'speed_limit' => (int) $user->speed_limit,
                'device_limit' => (int) ($user->device_limit ?? 0),
            ])
            ->values()
            ->all();
        $revision = hash('sha256', json_encode($users, JSON_UNESCAPED_SLASHES));
        if ($this->matchesEtag($request, $revision)) {
            return response()->json(null, 304);
        }

        return response()
            ->json(['revision' => $revision, 'users' => $users])
            ->header('ETag', "\"{$revision}\"");
    }

    public function report(Request $request): JsonResponse
    {
        $node = $this->node($request);
        $params = $request->validate([
            'report_id' => 'nullable|string|max:128',
            'traffic' => 'nullable|array',
            'traffic.*' => 'array|size:2',
            'traffic.*.0' => 'numeric|min:0',
            'traffic.*.1' => 'numeric|min:0',
            'alive' => 'nullable|array',
            'alive.*' => 'array',
            'alive.*.*' => 'string|max:128',
        ]);

        $reportId = $params['report_id'] ?? null;
        if ($reportId && !Cache::add($this->reportKey($node, $reportId), true, now()->addDay())) {
            return response()->json(['accepted' => true, 'duplicate' => true]);
        }

        $assignedUserIds = ServerService::getAvailableUsers($node)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->flip()
            ->all();
        $traffic = array_filter(
            $params['traffic'] ?? [],
            fn ($value, $userId) => isset($assignedUserIds[(string) $userId]),
            ARRAY_FILTER_USE_BOTH
        );
        $alive = array_filter(
            $params['alive'] ?? [],
            fn ($value, $userId) => isset($assignedUserIds[(string) $userId]),
            ARRAY_FILTER_USE_BOTH
        );

        ServerService::touchNode($node);
        if (!empty($traffic)) {
            ServerService::processTraffic($node, $traffic);
        }
        if (!empty($alive)) {
            ServerService::processAlive($node->id, $alive);
        }

        return response()->json(['accepted' => true, 'duplicate' => false]);
    }

    public function admitDevice(Request $request): JsonResponse
    {
        [$node, $params, $user] = $this->admissionRequest($request, true);
        $mode = $this->deviceMode($node);
        $result = $this->deviceLeases->admit(
            (int) $user->id,
            (int) ($user->device_limit ?? 0),
            $mode,
            $params['session_id'],
            $params['ip']
        );

        return response()->json([
            'allowed' => $result['allowed'],
            'device_count' => $result['count'],
            'limited' => $result['limited'],
            'mode' => $mode,
            'lease_ttl' => $result['limited'] ? TsunamiDeviceLeaseService::TTL : 0,
        ]);
    }

    public function renewDevice(Request $request): JsonResponse
    {
        [$node, $params, $user] = $this->admissionRequest($request, true, false);
        $result = $this->deviceLeases->renew(
            (int) $user->id,
            (int) ($user->device_limit ?? 0),
            $params['session_id']
        );

        return response()->json([
            'allowed' => $result['allowed'],
            'device_count' => $result['count'],
            'limited' => $result['limited'],
            'mode' => $this->deviceMode($node),
            'lease_ttl' => $result['limited'] ? TsunamiDeviceLeaseService::TTL : 0,
        ]);
    }

    public function releaseDevice(Request $request): JsonResponse
    {
        $node = $this->node($request);
        $params = $request->validate([
            'user_id' => 'required|integer|min:1',
            'session_id' => 'required|string|min:16|max:128',
        ]);
        $user = $this->assignedUser($node, (int) $params['user_id']);
        if (!$user) {
            return response()->json(['released' => true, 'device_count' => 0]);
        }

        $result = $this->deviceLeases->release((int) $user->id, $params['session_id']);
        return response()->json([
            'released' => $result['released'],
            'device_count' => $result['count'],
        ]);
    }

    private function admissionRequest(Request $request, bool $mustBeAvailable, bool $requireIp = true): array
    {
        $node = $this->node($request);
        $rules = [
            'user_id' => 'required|integer|min:1',
            'session_id' => 'required|string|min:16|max:128',
        ];
        if ($requireIp) {
            $rules['ip'] = 'required|string|max:128';
        }
        $params = $request->validate($rules);
        if ($requireIp) {
            $params['ip'] = TsunamiDeviceLeaseService::normalizeIP($params['ip']);
            if (!$params['ip']) {
                throw new ApiException('Invalid client IP', 422);
            }
        }

        $user = $mustBeAvailable
            ? ServerService::getAvailableUsers($node)->first(fn ($candidate) => (int) $candidate->id === (int) $params['user_id'])
            : $this->assignedUser($node, (int) $params['user_id']);
        if (!$user) {
            throw new ApiException('User is not available on this TSUNAMI node', 403);
        }

        return [$node, $params, $user];
    }

    private function assignedUser(Server $node, int $userId): ?User
    {
        $groupIds = $node->group_ids ?? [];
        if (empty($groupIds)) {
            return null;
        }
        return User::query()
            ->whereKey($userId)
            ->whereIn('group_id', $groupIds)
            ->first();
    }

    private function node(Request $request): Server
    {
        return $request->attributes->get('node_info');
    }

    private function deviceMode(Server $node): string
    {
        return data_get($node->protocol_settings, 'security.device_limit_mode') === 'session'
            ? 'session'
            : 'ip';
    }

    private function matchesEtag(Request $request, string $etag): bool
    {
        return str_contains($request->header('If-None-Match', ''), $etag);
    }

    private function reportKey(Server $node, string $reportId): string
    {
        return 'tsunami:report:' . $node->id . ':' . hash('sha256', $reportId);
    }
}
