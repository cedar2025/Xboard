<?php

namespace App\Observers;

use App\Models\Server;
use App\Services\NodeSyncService;

class ServerObserver
{
    public function updated(Server $server): void
    {
        if (
            $server->isDirty([
                'group_ids',
            ])
        ) {
            NodeSyncService::notifyUsersUpdatedByGroup($server->id);
        } else if (
            $server->isDirty([
                'server_port',
                'protocol_settings',
                'type',
                'route_ids',
                'custom_outbounds',
                'custom_routes',
                'cert_config',
            ])
        ) {
            NodeSyncService::notifyConfigUpdated($server->id);
        }
    }

    public function deleted(Server $server): void
    {
        NodeSyncService::notifyConfigUpdated($server->id);
    }
}
