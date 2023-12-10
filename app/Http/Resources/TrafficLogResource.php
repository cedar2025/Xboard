<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrafficLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            "d" => $this['d'],
            "u" => $this['u'],
            "record_at" =>  $this['record_at'],
            "server_rate" => $this['server_rate'],
        ];
        if(!config('hidden_features.enable_exposed_user_count_fix')) $data['user_id']= $this['user_id'];
        return $data;
    }
}
