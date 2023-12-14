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
            'version' => $this['version'],
            'name' => $this['name'],
            'rate' => $this['rate'],
            'tags' => $this['tags'],
            'is_online' => $this['is_online']
        ];
    }
}
