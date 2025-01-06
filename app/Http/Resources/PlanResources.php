<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResources extends JsonResource
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
            'group_id' => $this['group_id'],
            'name' => $this['name'],
            'content' => $this['content'],
            ...$this->transformPeriodPrices(),
            'capacity_limit' => $this->formatCapacityLimit(),
            'transfer_enable' => $this['transfer_enable'],
            'speed_limit' => $this['speed_limit'],
            'show' => (bool)$this['show'],
            'sell' => (bool)$this['sell'],
            'renew' => (bool)$this['renew'],
            'reset_traffic_method' => $this['reset_traffic_method'],
            'sort' => $this['sort'],
            'created_at' => $this['created_at'],
            'updated_at' => $this['updated_at']
        ];
    }

    /**
     * Transform period prices using PlanService mapping
     *
     * @return array<string, mixed>
     */
    protected function transformPeriodPrices(): array
    {
        $prices = [];
        foreach (Plan::LEGACY_PERIOD_MAPPING as $legacyPeriod => $newPeriod) {
            $prices[$legacyPeriod] = optional($this['prices'])[$newPeriod] ? (int)$this['prices'][$newPeriod] * 100 : null;
        }
        return $prices;
    }

    /**
     * Format the capacity limit value
     *
     * @return int|string|null
     */
    protected function formatCapacityLimit(): int|string|null
    {
        if ($this['capacity_limit'] === null) {
            return null;
        }

        if ($this['capacity_limit'] <= 0) {
            return __('Sold out');
        }

        return (int)$this['capacity_limit'];
    }
}
