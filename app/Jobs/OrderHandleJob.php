<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Utils\PlanPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OrderHandleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 5;
    protected $order;
    protected $tradeNo;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($tradeNo)
    {
        $this->onQueue('order_handle');
        $this->tradeNo = $tradeNo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $order = Order::where('trade_no', $this->tradeNo)
            ->lockForUpdate()
            ->first();
        if (!$order) return;
        $orderService = new OrderService($order);
        switch ($order->status) {
            // cancel
            case 0:
                $autoCancelTime = 3600 * 2;
                switch ($order->period) {
                    case PlanPeriod::MONTH_PRICE:
                        $autoCancelTime = 3600 * 24 * 3;
                        break;
                    case PlanPeriod::HALF_YEAR_PRICE:
                        $autoCancelTime = 3600 * 24 * 18;
                        break;
                    case PlanPeriod::YEAR_PRICE:
                        $autoCancelTime = 3600 * 24 * 36;
                        break;
                }

                if ($order->created_at <= (time() - $autoCancelTime)) {
                    $orderService->cancel();
                }

                break;
            case 1:
                $orderService->open();
                break;
        }
    }
}
