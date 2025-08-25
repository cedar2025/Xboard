<?php

use Illuminate\Support\Facades\Route;
use Plugin\StripeWeChatAlipay\Controllers\PaymentController;

Route::prefix('plugins/stripe-wechat-alipay')->group(function () {
    Route::get('/payment', [PaymentController::class, 'show'])->name('stripe-wechat-alipay.payment');
});