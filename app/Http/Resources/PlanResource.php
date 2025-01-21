<?php


namespace App\Http\Resources;

use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    private const PRICE_MULTIPLIER = 100;
    
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'group_id' => $this->resource['group_id'],
            'name' => $this->resource['name'],
            'content' => $this->resource['content'],
            ...$this->getPeriodPrices(),
            'capacity_limit' => $this->getFormattedCapacityLimit(),
            'transfer_enable' => $this->resource['transfer_enable'],
            'speed_limit' => $this->resource['speed_limit'],
            'show' => (bool) $this->resource['show'],
            'sell' => (bool) $this->resource['sell'],
            'renew' => (bool) $this->resource['renew'],
            'reset_traffic_method' => $this->resource['reset_traffic_method'],
            'sort' => $this->resource['sort'],
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at']
        ];
    }

    /**
     * Get transformed period prices using Plan mapping
     *
     * @return array<string, float|null>
     */
    protected function getPeriodPrices(): array
    {
        return collect(Plan::LEGACY_PERIOD_MAPPING)
            ->mapWithKeys(function (string $newPeriod, string $legacyPeriod): array {
                $price = $this->resource['prices'][$newPeriod] ?? null;
                return [
                    $legacyPeriod => $price !== null 
                        ? (float) $price * self::PRICE_MULTIPLIER 
                        : null
                ];
            })
            ->all();
    }

    /**
     * Get formatted capacity limit value
     *
     * @return int|string|null
     */
    protected function getFormattedCapacityLimit(): int|string|null
    {
        $limit = $this->resource['capacity_limit'];

        return match (true) {
            $limit === null => null,
            $limit <= 0 => __('Sold out'),
            default => (int) $limit,
        };
    }
} 