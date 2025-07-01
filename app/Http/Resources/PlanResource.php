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
            'tags' => $this->resource['tags'],
            'content' => $this->formatContent(),
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

    /**
     * Format content with template variables
     *
     * @return string
     */
    protected function formatContent(): string
    {
        $content = $this->resource['content'] ?? '';
        
        $replacements = [
            '{{transfer}}' => $this->resource['transfer_enable'],
            '{{speed}}' => $this->resource['speed_limit'] === NULL ? __('No Limit') : $this->resource['speed_limit'],
            '{{devices}}' => $this->resource['device_limit'] === NULL ? __('No Limit') : $this->resource['device_limit'],
            '{{reset_method}}' => $this->getResetMethodText(),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );
    }

    /**
     * Get reset method text
     *
     * @return string
     */
    protected function getResetMethodText(): string
    {
        $method = $this->resource['reset_traffic_method'];
        
        if ($method === Plan::RESET_TRAFFIC_FOLLOW_SYSTEM) {
            $method = admin_setting('reset_traffic_method', Plan::RESET_TRAFFIC_MONTHLY);
        }
        return match ($method) {
            Plan::RESET_TRAFFIC_FIRST_DAY_MONTH => __('First Day of Month'),
            Plan::RESET_TRAFFIC_MONTHLY => __('Monthly'),
            Plan::RESET_TRAFFIC_NEVER => __('Never'),
            Plan::RESET_TRAFFIC_FIRST_DAY_YEAR => __('First Day of Year'),
            Plan::RESET_TRAFFIC_YEARLY => __('Yearly'),
            default => __('Monthly')
        };
    }
}