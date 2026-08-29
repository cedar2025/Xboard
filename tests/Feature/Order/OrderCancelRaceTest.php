<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

// Regression tests for cedar2025/Xboard#1021:
// concurrent/repeated cancellation of a pending order must refund the
// balance deduction exactly once; repeated paid()/open() must not
// double-credit or double-extend.
class OrderCancelRaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'email' => 'race@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'uuid' => Str::uuid()->toString(),
            'token' => Str::random(32),
            'balance' => 0,
        ]);
        $this->plan = Plan::create([
            'group_id' => 1,
            'transfer_enable' => 10,
            'name' => 'test-plan',
            'show' => 1,
            'renew' => 1,
            'sort' => 0,
            'content' => '',
            'speed_limit' => null,
            'device_limit' => null,
        ]);
    }

    private function pendingOrder(int $balanceAmount, int $totalAmount = 1000): Order
    {
        return Order::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'period' => 'month_price',
            'trade_no' => 'TEST-' . Str::random(10),
            'total_amount' => $totalAmount,
            'balance_amount' => $balanceAmount,
            'type' => Order::TYPE_NEW_PURCHASE,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    public function test_second_cancel_does_not_refund_twice(): void
    {
        $order = $this->pendingOrder(500);

        $first = (new OrderService($order))->cancel();
        $this->assertTrue($first);
        $this->assertSame(500, $this->user->refresh()->balance);
        $this->assertSame(Order::STATUS_CANCELLED, (int) $order->refresh()->status);

        // Two concurrent requests both holding a stale pending model:
        // the loser must not refund again.
        $staleOrder = Order::find($order->id);
        $staleOrder->status = Order::STATUS_PENDING;
        $second = (new OrderService($staleOrder))->cancel();

        $this->assertFalse($second);
        $this->assertSame(500, $this->user->refresh()->balance);
    }

    public function test_cancel_after_paid_does_not_refund(): void
    {
        $order = $this->pendingOrder(500);
        $order->status = Order::STATUS_PROCESSING;
        $order->save();

        // Stale controller-side model still believes the order is pending.
        $staleOrder = Order::find($order->id);
        $staleOrder->status = Order::STATUS_PENDING;

        $result = (new OrderService($staleOrder))->cancel();

        $this->assertFalse($result);
        $this->assertSame(0, $this->user->refresh()->balance);
        $this->assertSame(Order::STATUS_PROCESSING, (int) $order->refresh()->status);
    }

    public function test_paid_twice_opens_once(): void
    {
        $order = $this->pendingOrder(0, 0);
        $service = new OrderService($order);

        $this->assertTrue($service->paid('callback-1'));
        $paidAt = $order->refresh()->paid_at;
        $expiredAtAfterFirst = $this->user->refresh()->expired_at;

        $this->assertTrue($service->paid('callback-2'));

        $this->assertSame($paidAt, $order->refresh()->paid_at);
        $this->assertSame($expiredAtAfterFirst, $this->user->refresh()->expired_at);
        $this->assertSame(Order::STATUS_COMPLETED, (int) $order->refresh()->status);
    }
}
