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
            'id' => $this->id,
            'invite_user_id' => $this->invite_user_id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'coupon_id' => $this->coupon_id,
            'payment_id' => $this->payment_id,
            'type' => $this->type,
            'period' => PlanService::getLegacyPeriod((string)$this->period),
            'trade_no' => $this->trade_no,
            'callback_no' => $this->callback_no,
            'total_amount' => $this->total_amount,
            'handling_amount' => $this->handling_amount,
            'discount_amount' => $this->discount_amount,
            'surplus_amount' => $this->surplus_amount,
            'refund_amount' => $this->refund_amount,
            'balance_amount' => $this->balance_amount,
            'surplus_order_ids' => $this->surplus_order_ids,
            'status' => $this->status,
            'commission_status' => $this->commission_status,
            'commission_balance' => $this->commission_balance,
            'actual_commission_balance' => $this->actual_commission_balance,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'try_out_plan_id' => $this->resource['try_out_plan_id'] ?? null,
            'surplus_orders' => $this->resource['surplus_orders'] ?? null,
            'plan' => $this->whenLoaded('plan', fn() => PlanResource::make($this->plan)),
            'payment' => $this->whenLoaded('payment', fn() => $this->payment ? [
                'id' => $this->payment->id,
                'name' => $this->payment->name,
                'payment' => $this->payment->payment,
                'icon' => $this->payment->icon,
            ] : null),
        ];
    }
}
