<?php

namespace Plugin\StripeWeChatAlipay\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function show(Request $request): View
    {
        // 从请求中获取支付数据
        $paymentData = $request->all();
        
        // 处理支付方法列表
        if (isset($paymentData['payment_methods']) && is_string($paymentData['payment_methods'])) {
            $paymentData['payment_methods'] = explode(',', $paymentData['payment_methods']);
        }
        
        // 模拟订单数据（实际项目中应该从数据库获取）
        $order = [
            'trade_no' => $paymentData['trade_no'] ?? 'unknown',
            'total_amount' => $paymentData['amount'] ?? 0,
        ];
        
        return view('StripeWeChatAlipay::payment', [
            'order' => $order,
            'paymentData' => $paymentData
        ]);
    }
}