<?php

namespace Tests\Feature\Resources;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    public function test_serializes_only_whitelisted_fields(): void
    {
        $order = $this->makeOrder();

        $payload = (new OrderResource($order))->toArray(Request::create('/'));

        $expected = [
            'id', 'invite_user_id', 'user_id', 'plan_id', 'coupon_id', 'payment_id',
            'type', 'period', 'trade_no', 'callback_no', 'total_amount', 'handling_amount',
            'discount_amount', 'surplus_amount', 'refund_amount', 'balance_amount',
            'surplus_order_ids', 'status', 'commission_status', 'commission_balance',
            'actual_commission_balance', 'paid_at', 'created_at', 'updated_at',
            'try_out_plan_id', 'surplus_orders', 'plan', 'payment',
        ];

        sort($expected);
        $actual = array_keys($payload);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_unknown_attributes_are_not_leaked(): void
    {
        $order = $this->makeOrder();
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'secret_internal_field' => 'must-not-leak',
        ]), true);

        $payload = (new OrderResource($order))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('secret_internal_field', $payload);
    }

    public function test_loaded_payment_relation_excludes_config(): void
    {
        $order = $this->makeOrder();
        $payment = new Payment([
            'name' => 'Stripe Test',
            'payment' => 'StripeCheckout',
            'icon' => 'stripe.png',
            'config' => ['stripe_sk_live' => 'sk_live_THIS_IS_SECRET'],
        ]);
        $payment->id = 7;
        $payment->exists = true;
        $order->setRelation('payment', $payment);

        $payload = (new OrderResource($order))->toArray(Request::create('/'));

        $this->assertSame(7, $payload['payment']['id']);
        $this->assertSame('Stripe Test', $payload['payment']['name']);
        $this->assertArrayNotHasKey('config', $payload['payment']);
        $this->assertStringNotContainsString('sk_live_THIS_IS_SECRET', json_encode($payload));
    }

    public function test_loaded_plan_relation_uses_plan_resource(): void
    {
        $order = $this->makeOrder();
        $plan = new Plan([
            'group_id' => 1,
            'name' => 'Pro Plan',
            'tags' => null,
            'content' => 'desc',
            'prices' => ['monthly' => 9.9],
            'capacity_limit' => null,
            'transfer_enable' => 100,
            'speed_limit' => null,
            'device_limit' => null,
            'show' => true,
            'sell' => true,
            'renew' => true,
            'reset_traffic_method' => 0,
            'sort' => 1,
        ]);
        $plan->id = 3;
        $plan->exists = true;
        $order->setRelation('plan', $plan);

        $payload = (new OrderResource($order))->toArray(Request::create('/'));
        $planPayload = $payload['plan']->resolve(Request::create('/'));

        $expected = [
            'id', 'group_id', 'name', 'tags', 'content',
            'month_price', 'quarter_price', 'half_year_price', 'year_price',
            'two_year_price', 'three_year_price', 'onetime_price', 'reset_price',
            'capacity_limit', 'transfer_enable', 'speed_limit', 'device_limit',
            'show', 'sell', 'renew', 'reset_traffic_method', 'sort',
            'created_at', 'updated_at',
        ];
        sort($expected);
        $actual = array_keys($planPayload);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_unloaded_relations_are_omitted(): void
    {
        $order = $this->makeOrder();

        $payload = (new OrderResource($order))->toArray(Request::create('/'));

        $this->assertInstanceOf(\Illuminate\Http\Resources\MissingValue::class, $payload['plan']);
        $this->assertInstanceOf(\Illuminate\Http\Resources\MissingValue::class, $payload['payment']);
    }

    private function makeOrder(): Order
    {
        $order = new Order([
            'invite_user_id' => null,
            'user_id' => 1,
            'plan_id' => 2,
            'coupon_id' => null,
            'payment_id' => 7,
            'type' => Order::TYPE_NEW_PURCHASE,
            'period' => 'month_price',
            'trade_no' => 'TEST-1',
            'callback_no' => null,
            'total_amount' => 100,
            'status' => Order::STATUS_PENDING,
        ]);
        $order->id = 42;
        $order->exists = true;

        return $order;
    }
}
