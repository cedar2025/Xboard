<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'type' => $this['type'],
            'version' => $this['version'] ?? null,
            'name' => $this['name'],
            'rate' => $this['rate'],
            'tags' => $this['tags'],
            'is_online' => $this['is_online'],
            'cache_key' => $this['cache_key'],
            'last_check_at' => $this['last_check_at'],
            'load_users' => $this['load_users'] ?? 0,
            'bandwidth' => $this['load_status']['bandwidth'] ?? [
                'up' => 0,
                'down' => 0
            ],
            'utilization' => $this['load_status']['utilization'] ?? [
                'fullness_score' => 0,
                'is_full' => false,
                'capacity_settings' => [
                    'bandwidth_capacity_mbps' => 0,
                    'session_ceiling' => 0,
                ]
            ],
        ];
    }
}
