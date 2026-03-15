<?php

namespace App\Observers;

use App\Models\Server;
use App\Models\ServerRoute;
use App\Services\NodeSyncService;

class ServerRouteObserver
{
    public function updated(ServerRoute $route): void
    {
        $this->notifyAffectedNodes($route->id);
    }

    public function deleted(ServerRoute $route): void
    {
        $this->notifyAffectedNodes($route->id);
    }

    private function notifyAffectedNodes(int $routeId): void
    {
        $servers = Server::where('show', 1)->get()->filter(
            fn ($s) => in_array($routeId, $s->route_ids ?? [])
        );

        foreach ($servers as $server) {
            NodeSyncService::notifyConfigUpdated($server->id);
        }
    }
}
