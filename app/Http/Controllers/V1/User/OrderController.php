<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\OrderSave;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PlanService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function fetch(Request $request)
    {
        $model = Order::where('user_id', $request->user['id'])
            ->orderBy('created_at', 'DESC');
        if ($request->input('status') !== null) {
            $model->where('status', $request->input('status'));
        }
        $order = $model->get();
        $plan = Plan::get();
        for ($i = 0; $i < count($order); $i++) {
            for ($x = 0; $x < count($plan); $x++) {
                if ($order[$i]['plan_id'] === $plan[$x]['id']) {
                    $order[$i]['plan'] = $plan[$x];
                }
            }
        }
        return $this->success($order->makeHidden(['id', 'user_id']));
    }

    public function detail(Request $request)
    {
        $order = Order::where('user_id', $request->user['id'])
            ->where('trade_no', $request->input('trade_no'))
            ->first();
        if (!$order) {
            return $this->fail([400, __('Order does not exist or has been paid')]);
        }
        $order['plan'] = Plan::find($order->plan_id);
        $order['try_out_plan_id'] = (int)admin_setting('try_out_plan_id');
        if (!$order['plan']) {
            return $this->fail([400, __('Subscription plan does not exist')]);
        }
        if ($order->surplus_order_ids) {
            $order['surplus_orders'] = Order::whereIn('id', $order->surplus_order_ids)->get();
        }
        return $this->success($order);
    }

    public function save(OrderSave $request)
    {
        $userService = new UserService();
        if ($userService->isNotCompleteOrderByUserId($request->user['id'])) {
            return $this->fail([400, __('You have an unpaid or pending order, please try again later or cancel it')]);
        }

        $planService = new PlanService($request->input('plan_id'));

        $plan = $planService->plan;
        $user = User::find($request->user['id']);

        if (!$plan) {
            return $this->fail([400, __('Subscription plan does not exist')]);
        }

        if ($user->plan_id !== $plan->id && !$planService->haveCapacity() && $request->input('period') !== 'reset_price') {
            throw new ApiException(__('Current product is sold out'));
        }

        if ($plan[$request->input('period')] === NULL) {
            return $this->fail([400, __('This payment period cannot be purchased, please choose another period')]);
        }

        if ($request->input('period') === 'reset_price') {
            if (!$userService->isAvailable($user) || $plan->id !== $user->plan_id) {
                return $this->fail([400, __('Subscription has expired or no active subscription, unable to purchase Data Reset Package')]);
            }
        }

        if ((!$plan->show && !$plan->renew) || (!$plan->show && $user->plan_id !== $plan->id)) {
            if ($request->input('period') !== 'reset_price') {
                return $this->fail([400, __('This subscription has been sold out, please choose another subscription')]);
            }
        }

        if (!$plan->renew && $user->plan_id == $plan->id && $request->input('period') !== 'reset_price') {
            return $this->fail([400, __('This subscription cannot be renewed, please change to another subscription')]);
        }


        if (!$plan->show && $plan->renew && !$userService->isAvailable($user)) {
            return $this->fail([400, __('This subscription has expired, please change to another subscription')]);
        }

        try{
            DB::beginTransaction();
            $order = new Order();
            $orderService = new OrderService($order);
            $order->user_id = $request->user['id'];
            $order->plan_id = $plan->id;
            $order->period = $request->input('period');
            $order->trade_no = Helper::generateOrderNo();
            $order->total_amount = $plan[$request->input('period')];

            if ($request->input('coupon_code')) {
                $couponService = new CouponService($request->input('coupon_code'));
                if (!$couponService->use($order)) {
                    return $this->fail([400, __('Coupon failed')]);
                }
                $order->coupon_id = $couponService->getId();
            }

            $orderService->setVipDiscount($user);
            $orderService->setOrderType($user);
            $orderService->setInvite($user);

            if ($user->balance && $order->total_amount > 0) {
                $remainingBalance = $user->balance - $order->total_amount;
                $userService = new UserService();
                if ($remainingBalance > 0) {
                    if (!$userService->addBalance($order->user_id, - $order->total_amount)) {
                        return $this->fail([400, __('Insufficient balance')]);
                    }
                    $order->balance_amount = $order->total_amount;
                    $order->total_amount = 0;
                } else {
                    if (!$userService->addBalance($order->user_id, - $user->balance)) {
                        return $this->fail([400, __('Insufficient balance')]);
                    }
                    $order->balance_amount = $user->balance;
                    $order->total_amount = $order->total_amount - $user->balance;
                }
            }

            if (!$order->save()) {
                return $this->fail([400, __('Failed to create order')]);
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            throw $e;
        }

        return $this->success($order->trade_no);
    }

    public function checkout(Request $request)
    {
        $tradeNo = $request->input('trade_no');
        $method = $request->input('method');
        $order = Order::where('trade_no', $tradeNo)
            ->where('user_id', $request->user['id'])
            ->where('status', 0)
            ->first();
        if (!$order) {
            return $this->fail([400, __('Order does not exist or has been paid')]);
        }
        // free process
        if ($order->total_amount <= 0) {
            $orderService = new OrderService($order);
            if (!$orderService->paid($order->trade_no)) return $this->fail([400, '支付失败']);
            return response([
                'type' => -1,
                'data' => true
            ]);
        }
        $payment = Payment::find($method);
        if (!$payment || $payment->enable !== 1) return $this->fail([400, __('Payment method is not available')]);
        $paymentService = new PaymentService($payment->payment, $payment->id);
        $order->handling_amount = NULL;
        if ($payment->handling_fee_fixed || $payment->handling_fee_percent) {
            $order->handling_amount = round(($order->total_amount * ($payment->handling_fee_percent / 100)) + $payment->handling_fee_fixed);
        }
        $order->payment_id = $method;
        if (!$order->save()) return $this->fail([400, __('Request failed, please try again later')]);
        $result = $paymentService->pay([
            'trade_no' => $tradeNo,
            'total_amount' => isset($order->handling_amount) ? ($order->total_amount + $order->handling_amount) : $order->total_amount,
            'user_id' => $order->user_id,
            'stripe_token' => $request->input('token')
        ]);
        return response([
            'type' => $result['type'],
            'data' => $result['data']
        ]);
    }

    public function check(Request $request)
    {
        $tradeNo = $request->input('trade_no');
        $order = Order::where('trade_no', $tradeNo)
            ->where('user_id', $request->user['id'])
            ->first();
        if (!$order) {
            return $this->fail([400, __('Order does not exist')]);
        }
        return $this->success($order->status);
    }

    public function getPaymentMethod()
    {
        $methods = Payment::select([
            'id',
            'name',
            'payment',
            'icon',
            'handling_fee_fixed',
            'handling_fee_percent'
        ])
            ->where('enable', 1)
            ->orderBy('sort', 'ASC')
            ->get();

        return $this->success($methods);
    }

    public function cancel(Request $request)
    {
        if (empty($request->input('trade_no'))) {
            return $this->fail([422, __('Invalid parameter')]);
        }
        $order = Order::where('trade_no', $request->input('trade_no'))
            ->where('user_id', $request->user['id'])
            ->first();
        if (!$order) {
            return $this->fail([400, __('Order does not exist')]);
        }
        if ($order->status !== 0) {
            return $this->fail([400, __('You can only cancel pending orders')]);
        }
        $orderService = new OrderService($order);
        if (!$orderService->cancel()) {
            return $this->fail([400, __('Cancel failed')]);
        }
        return $this->success(true);
    }
}
