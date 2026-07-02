<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrafficPackageResource extends JsonResource
{
    private const PRICE_MULTIPLIER = 100;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'content' => $this->resource['content'],
            'transfer_enable' => $this->resource['transfer_enable'],
            'price' => (float) $this->resource['price'] * self::PRICE_MULTIPLIER,
            'group_id' => $this->resource['group_id'],
            'speed_limit' => $this->resource['speed_limit'],
            'device_limit' => $this->resource['device_limit'],
            'show' => (bool) $this->resource['show'],
            'sell' => (bool) $this->resource['sell'],
            'sort' => $this->resource['sort'],
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at'],
        ];
    }
}
