<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Plugin\HookManager;

class PaymentController extends Controller
{
    public function notify($method, $uuid, Request $request)
    {
        HookManager::call('payment.notify.before', [$method, $uuid, $request]);
        try {
            $paymentService = new PaymentService($method, null, $uuid);
            $verify = $paymentService->notify($request->input());
            // Some gateways acknowledge pending notifications without confirming payment.
            if (is_string($verify)) {
                return $verify;
            }
            if (!$verify) {
                HookManager::call('payment.notify.failed', [$method, $uuid, $request]);
                return $this->fail([422, 'verify error']);
            }
            HookManager::call('payment.notify.verified', $verify);
            if (!$this->handle($verify, $paymentService)) {
                return $this->fail([400, 'handle error']);
            }
            return (isset($verify['custom_result']) ? $verify['custom_result'] : 'success');
        } catch (\Throwable $e) {
            Log::warning('Payment notification rejected', ['exception' => get_class($e)]);
            return $this->fail([500, 'fail']);
        }
    }

    private function handle(array $verify, PaymentService $paymentService): bool
    {
        foreach (['trade_no', 'callback_no'] as $field) {
            if (!is_string($verify[$field] ?? null) || trim($verify[$field]) === '') {
                return false;
            }
        }
        [$accepted, $paidOrder] = DB::transaction(function () use ($verify, $paymentService) {
            // Serialize callbacks for one payment configuration, including receipt reuse checks.
            $payment = Payment::whereKey($paymentService->getPaymentId())->lockForUpdate()->first();
            if (!$payment || !$payment->enable || $payment->payment !== $paymentService->method) {
                return [false, null];
            }
            $order = Order::where('trade_no', $verify['trade_no'])->lockForUpdate()->first();
            if (!$order || (int) $order->payment_id !== (int) $payment->id
                || (int) $order->total_amount <= 0 || (int) $order->handling_amount < 0) {
                return [false, null];
            }
            $expectedAmount = (int) $order->total_amount + (int) $order->handling_amount;
            if (($payment->payment === 'EPay' || array_key_exists('paid_amount', $verify))
                && (!is_int($verify['paid_amount'] ?? null) || $verify['paid_amount'] !== $expectedAmount)) {
                return [false, null];
            }
            if (Order::where('payment_id', $payment->id)
                ->where('callback_no', $verify['callback_no'])
                ->where('id', '!=', $order->id)->exists()) {
                return [false, null];
            }
            if ((int) $order->status !== Order::STATUS_PENDING) {
                return [in_array((int) $order->status, [Order::STATUS_PROCESSING,
                    Order::STATUS_COMPLETED, Order::STATUS_DISCOUNTED], true)
                    && $order->callback_no === $verify['callback_no'], null];
            }
            if (!(new OrderService($order))->paid($verify['callback_no'])) {
                throw new \RuntimeException('Payment confirmation failed');
            }
            return [true, $order->fresh()];
        });
        if ($paidOrder) {
            HookManager::call('payment.notify.success', $paidOrder);
        }
        return $accepted;
    }
}
