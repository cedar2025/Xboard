<?php

use Illuminate\Support\Facades\Route;
use Plugin\Stripe\Controllers\PaymentController;

Route::prefix('plugins/stripe')->group(function () {
    Route::get('/payment', [PaymentController::class, 'show'])->name('stripe.payment');
});