<?php

namespace App\Http\Resources;

use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResources extends JsonResource
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
            'period' => PlanService::getLegacyPeriod($this->period),
            'plan' => PlanResources::make($this->plan),
        ];
    }
}
