<?php

namespace App\Jobs;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PlanService;
use App\Services\UserService;
use App\Utils\Helper;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AutoGenerateRenewalOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    protected $user;

    /**
     * Create a new job instance.
     *
     * @param $user User model
     * @return void
     */
    public function __construct($user)
    {
        $this->onQueue('auto_generate_renewal_orders');
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * find user that have normal status,
     * do not have any renewal order,
     * and has a plan which will be expired in 7 days.
     *
     * @return void
     * @throws ApiException
     */
    public function handle(): void
    {
        $user = $this->user;

        // check if user is available
        $userService = new UserService();
        if (!$userService->isAvailable($user)) {
            // user is not available
            return;
        }

        // check if user has a plan
        $planService = new PlanService($user->plan_id);
        $plan = $planService->plan;
        if (!$plan || !$user->last_plan_period) {
            // user has no plan
            return;
        }

        // check if plan will expire in 7 days
        $now = time();
        $expireTime = $user->expire_at; // plan expire time, unix timestamp seconds
        if ($expireTime - $now > 7 * 24 * 3600) {
            // plan will not expire in 7 days
            return;
        }

        // check if user has any renewal order
        if ($userService->hasRenewalOrder($user->id)) {
            // user has renewal order
            return;
        }

        // create renewal order
        try {
            DB::beginTransaction();
            $order = new Order();
            $orderService = new OrderService($order);
            $order->user_id = $user->id;
            $order->plan_id = $plan->id;
            $order->period = $user->last_plan_period;
            $order->trade_no = Helper::generateOrderNo();
            $order->total_amount = $plan[$user->last_plan_period];

            $orderService->setVipDiscount($user);
            $orderService->setOrderType($user);
            $orderService->setInvite($user);

            if ($user->balance && $order->total_amount > 0) {
                $remainingBalance = $user->balance - $order->total_amount;
                $userService = new UserService();
                if ($remainingBalance > 0) {
                    if (!$userService->addBalance($order->user_id, -$order->total_amount)) {
                        throw new ApiException(__('Insufficient balance'));
                    }
                    $order->balance_amount = $order->total_amount;
                    $order->total_amount = 0;
                } else {
                    if (!$userService->addBalance($order->user_id, -$user->balance)) {
                        throw new ApiException(__('Insufficient balance'));
                    }
                    $order->balance_amount = $user->balance;
                    $order->total_amount = $order->total_amount - $user->balance;
                }
            }

            if (!$order->save()) {
                throw new ApiException(__('Failed to create order'));
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // TODO: send email to user
    }
}
