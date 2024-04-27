<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Jobs\OrderHandleJob;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    CONST STR_TO_TIME = [
        'month_price' => 1,
        'quarter_price' => 3,
        'half_year_price' => 6,
        'year_price' => 12,
        'two_year_price' => 24,
        'three_year_price' => 36
    ];
    public $order;
    public $user;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function open()
    {
        $order = $this->order;
        $this->user = User::find($order->user_id);
        $plan = Plan::find($order->plan_id);

        if ($order->refund_amount) {
            $this->user->balance = $this->user->balance + $order->refund_amount;
        }
        try{
            DB::beginTransaction();
            if ($order->surplus_order_ids) {
                Order::whereIn('id', $order->surplus_order_ids)->update([
                    'status' => Order::STATUS_DISCOUNTED
                ]);
            }
            switch ((string)$order->period) {
                case 'onetime_price':
                    $this->buyByOneTime($plan);
                    break;
                case 'reset_price':
                    $this->buyByResetTraffic();
                    break;
                default:
                    $this->buyByPeriod($order, $plan);
            }

            switch ((int)$order->type) {
                case 1:
                    $this->openEvent(admin_setting('new_order_event_id', 0));
                    break;
                case 2:
                    $this->openEvent(admin_setting('renew_order_event_id', 0));
                    break;
                case 3:
                    $this->openEvent(admin_setting('change_order_event_id', 0));
                    break;
            }

            $this->setSpeedLimit($plan->speed_limit);

            if (!$this->user->save()) {
                throw new \Exception('用户信息保存失败');
            }
            $order->status = Order::STATUS_COMPLETED;
            if (!$order->save()) {
                throw new \Exception('订单信息保存失败');
            }
            DB::commit();
        }catch(\Exception $e){
            DB::rollBack();
            \Log::error($e);
            throw new ApiException('开通失败');
        }
    }


    public function setOrderType(User $user)
    {
        $order = $this->order;
        if ($order->period === 'reset_price') {
            $order->type = Order::TYPE_RESET_TRAFFIC;
        } else if ($user->plan_id !== NULL && $order->plan_id !== $user->plan_id && ($user->expired_at > time() || $user->expired_at === NULL)) {
            if (!(int)admin_setting('plan_change_enable', 1)) throw new ApiException('目前不允许更改订阅，请联系客服或提交工单操作');
            $order->type = Order::TYPE_UPGRADE;
            if ((int)admin_setting('surplus_enable', 1)) $this->getSurplusValue($user, $order);
            if ($order->surplus_amount >= $order->total_amount) {
                $order->refund_amount = $order->surplus_amount - $order->total_amount;
                $order->total_amount = 0;
            } else {
                $order->total_amount = $order->total_amount - $order->surplus_amount;
            }
        } else if ($user->expired_at > time() && $order->plan_id == $user->plan_id) { // 用户订阅未过期且购买订阅与当前订阅相同 === 续费
            $order->type = Order::TYPE_RENEWAL;
        } else { // 新购
            $order->type = Order::TYPE_NEW_PURCHASE;
        }
    }

    public function setVipDiscount(User $user)
    {
        $order = $this->order;
        if ($user->discount) {
            $order->discount_amount = $order->discount_amount + ($order->total_amount * ($user->discount / 100));
        }
        $order->total_amount = $order->total_amount - $order->discount_amount;
    }

    public function setInvite(User $user):void
    {
        $order = $this->order;
        if ($user->invite_user_id && ($order->total_amount <= 0)) return;
        $order->invite_user_id = $user->invite_user_id;
        $inviter = User::find($user->invite_user_id);
        if (!$inviter) return;
        $isCommission = false;
        switch ((int)$inviter->commission_type) {
            case 0:
                $commissionFirstTime = (int)admin_setting('commission_first_time_enable', 1);
                $isCommission = (!$commissionFirstTime || ($commissionFirstTime && !$this->haveValidOrder($user)));
                break;
            case 1:
                $isCommission = true;
                break;
            case 2:
                $isCommission = !$this->haveValidOrder($user);
                break;
        }

        if (!$isCommission) return;
        if ($inviter && $inviter->commission_rate) {
            $order->commission_balance = $order->total_amount * ($inviter->commission_rate / 100);
        } else {
            $order->commission_balance = $order->total_amount * (admin_setting('invite_commission', 10) / 100);
        }
    }

    private function haveValidOrder(User $user)
    {
        return Order::where('user_id', $user->id)
            ->whereNotIn('status', [0, 2])
            ->first();
    }

    private function getSurplusValue(User $user, Order $order)
    {
        if ($user->expired_at === NULL) {
            $this->getSurplusValueByOneTime($user, $order);
        } else {
            $this->getSurplusValueByPeriod($user, $order);
        }
    }


    private function getSurplusValueByOneTime(User $user, Order $order)
    {
        $lastOneTimeOrder = Order::where('user_id', $user->id)
            ->where('period', 'onetime_price')
            ->where('status', Order::STATUS_COMPLETED)
            ->orderBy('id', 'DESC')
            ->first();
        if (!$lastOneTimeOrder) return;
        $nowUserTraffic = $user->transfer_enable / 1073741824;
        if (!$nowUserTraffic) return;
        $paidTotalAmount = ($lastOneTimeOrder->total_amount + $lastOneTimeOrder->balance_amount);
        if (!$paidTotalAmount) return;
        $trafficUnitPrice = $paidTotalAmount / $nowUserTraffic;
        $notUsedTraffic = $nowUserTraffic - (($user->u + $user->d) / 1073741824);
        $result = $trafficUnitPrice * $notUsedTraffic;
        $orderModel = Order::where('user_id', $user->id)->where('period', '!=', 'reset_price')->where('status', Order::STATUS_COMPLETED);
        $order->surplus_amount = $result > 0 ? $result : 0;
        $order->surplus_order_ids = array_column($orderModel->get()->toArray(), 'id');
    }

    private function getSurplusValueByPeriod(User $user, Order $order)
    {
        $orders = Order::where('user_id', $user->id)
            ->whereNotIn('period', ['reset_price', 'onetime_price'])
            ->where('status', Order::STATUS_COMPLETED)
            ->get()
            ->toArray();
        if (!$orders) return;
        $orderAmountSum = 0;
        $orderMonthSum = 0;
        $lastValidateAt = 0;
        foreach ($orders as $item) {
            $period = self::STR_TO_TIME[$item['period']];
            if (strtotime("+{$period} month", $item['created_at']) < time()) continue;
            $lastValidateAt = $item['created_at'];
            $orderMonthSum = $period + $orderMonthSum;
            $orderAmountSum = $orderAmountSum + ($item['total_amount'] + $item['balance_amount'] + $item['surplus_amount'] - $item['refund_amount']);
        }
        if (!$lastValidateAt) return;
        $expiredAtByOrder = strtotime("+{$orderMonthSum} month", $lastValidateAt);
        if ($expiredAtByOrder < time()) return;
        $orderSurplusSecond = $expiredAtByOrder - time();
        $orderRangeSecond = $expiredAtByOrder - $lastValidateAt;
        $avgPrice = $orderAmountSum / $orderRangeSecond;
        $orderSurplusAmount = $avgPrice * $orderSurplusSecond;
        if (!$orderSurplusSecond || !$orderSurplusAmount) return;
        $order->surplus_amount = $orderSurplusAmount > 0 ? $orderSurplusAmount : 0;
        $order->surplus_order_ids = array_column($orders, 'id');
    }

    public function paid(string $callbackNo)
    {
        $order = $this->order;
        if ($order->status !== Order::STATUS_PENDING) return true;
        $order->status = Order::STATUS_PROCESSING;
        $order->paid_at = time();
        $order->callback_no = $callbackNo;
        if (!$order->save()) return false;
        try {
            OrderHandleJob::dispatchSync($order->trade_no);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public function cancel():bool
    {
        $order = $this->order;
        try {
            DB::beginTransaction();
            $order->status = Order::STATUS_CANCELLED;
            if (!$order->save()) {
                throw new \Exception('Failed to save order status.');
            }
            if ($order->balance_amount) {
                $userService = new UserService();
                if (!$userService->addBalance($order->user_id, $order->balance_amount)) {
                    throw new \Exception('Failed to add balance.');
                }
            }
            DB::commit();
            return true;
        }catch(\Exception $e){
            DB::rollBack();
            \Log::error($e);
            return false;
        }
    }

    private function setSpeedLimit($speedLimit)
    {
        $this->user->speed_limit = $speedLimit;
    }

    private function buyByResetTraffic()
    {
        $this->user->u = 0;
        $this->user->d = 0;
    }

    private function buyByPeriod(Order $order, Plan $plan)
    {
        // change plan process
        if ((int)$order->type === 3) {
            $this->user->expired_at = time();
        }
        $this->user->transfer_enable = $plan->transfer_enable * 1073741824;
        // 从一次性转换到循环
        if ($this->user->expired_at === NULL) $this->buyByResetTraffic();
        // 新购
        if ($order->type === 1) $this->buyByResetTraffic();
        $this->user->plan_id = $plan->id;
        $this->user->group_id = $plan->group_id;
        $this->user->expired_at = $this->getTime($order->period, $this->user->expired_at);
    }

    private function buyByOneTime(Plan $plan)
    {
        $this->buyByResetTraffic();
        $this->user->transfer_enable = $plan->transfer_enable * 1073741824;
        $this->user->plan_id = $plan->id;
        $this->user->group_id = $plan->group_id;
        $this->user->expired_at = NULL;
    }

    private function getTime($str, $timestamp)
    {
        if ($timestamp < time()) {
            $timestamp = time();
        }
        switch ($str) {
            case 'month_price':
                return strtotime('+1 month', $timestamp);
            case 'quarter_price':
                return strtotime('+3 month', $timestamp);
            case 'half_year_price':
                return strtotime('+6 month', $timestamp);
            case 'year_price':
                return strtotime('+12 month', $timestamp);
            case 'two_year_price':
                return strtotime('+24 month', $timestamp);
            case 'three_year_price':
                return strtotime('+36 month', $timestamp);
        }
    }

    private function openEvent($eventId)
    {
        switch ((int) $eventId) {
            case 0:
                break;
            case 1:
                $this->buyByResetTraffic();
                break;
        }
    }
}
