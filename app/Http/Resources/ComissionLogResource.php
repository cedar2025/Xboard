<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComissionLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=> $this['id'],
            "order_amount" => $this['order_amount'],
            "trade_no" => $this['trade_no'],
            "get_amount" => $this['get_amount'],
            "created_at" => $this['created_at']
        ];
    }
}
