<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'period' => PlanService::getLegacyPeriod((string)$this->period),
            'plan' => $this->whenLoaded('plan', fn() => PlanResource::make($this->plan)),
        ];
    }
}
