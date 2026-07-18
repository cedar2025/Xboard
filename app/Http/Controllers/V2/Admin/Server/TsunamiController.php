<?php

namespace App\Http\Controllers\V2\Admin\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\TsunamiCredential;
use Illuminate\Http\Request;

class TsunamiController extends Controller
{
    public function credential(Request $request)
    {
        $server = $this->server($request);
        $credential = TsunamiCredential::where('server_id', $server->id)->first();

        return $this->success([
            'node_id' => $server->id,
            'enrolled_at' => $credential?->enrolled_at,
            'revoked_at' => $credential?->revoked_at,
            'enrollment_expires_at' => $credential?->enrollment_expires_at,
            'has_credential' => $credential?->credential_hash !== null,
            'has_enrollment' => $credential?->enrollment_hash !== null,
        ]);
    }

    public function issueEnrollment(Request $request)
    {
        $params = $request->validate([
            'server_id' => 'required|integer',
            'expires_in' => 'nullable|integer|min:60|max:86400',
        ]);
        $server = $this->server($request, $params['server_id']);
        $credential = TsunamiCredential::firstOrNew(['server_id' => $server->id]);
        $token = self::newToken();

        $credential->fill([
            'enrollment_hash' => hash('sha256', $token),
            'enrollment_expires_at' => now()->addSeconds($params['expires_in'] ?? 3600),
        ])->save();

        return $this->success([
            'node_id' => $server->id,
            'enrollment_token' => $token,
            'expires_at' => $credential->enrollment_expires_at,
            'install_command' => $this->installCommand($request, $server, $token),
        ]);
    }

    public function rotateCredential(Request $request)
    {
        $server = $this->server($request);
        $credential = TsunamiCredential::firstOrNew(['server_id' => $server->id]);
        $token = self::newToken();

        $credential->fill([
            'credential_hash' => hash('sha256', $token),
            'enrollment_hash' => null,
            'enrollment_expires_at' => null,
            'enrolled_at' => now(),
            'revoked_at' => null,
        ])->save();

        return $this->success([
            'node_id' => $server->id,
            'credential' => $token,
        ]);
    }

    public function revokeCredential(Request $request)
    {
        $server = $this->server($request);
        $credential = TsunamiCredential::where('server_id', $server->id)->first();
        if ($credential) {
            $credential->forceFill([
                'revoked_at' => now(),
                'enrollment_hash' => null,
                'enrollment_expires_at' => null,
            ])->save();
        }

        return $this->success(true);
    }

    private function server(Request $request, ?int $serverId = null): Server
    {
        $serverId ??= (int) $request->input('server_id');
        $server = Server::find($serverId);
        if (!$server || $server->type !== Server::TYPE_TSUNAMI) {
            throw new ApiException('TSUNAMI node does not exist');
        }
        return $server;
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function installCommand(Request $request, Server $server, string $token): string
    {
        $panelUrl = rtrim((string) (admin_setting('app_url') ?: $request->getSchemeAndHttpHost()), '/');
        $installerUrl = 'https://raw.githubusercontent.com/RavenholmAlpha/tsunami/main/scripts/install-node.sh';

        return sprintf(
            'curl -fsSL %s | sudo env TSUNAMI_XBOARD_URL=%s TSUNAMI_NODE_ID=%s TSUNAMI_ENROLLMENT_TOKEN=%s bash',
            escapeshellarg($installerUrl),
            escapeshellarg($panelUrl),
            escapeshellarg((string) $server->id),
            escapeshellarg($token)
        );
    }
}
