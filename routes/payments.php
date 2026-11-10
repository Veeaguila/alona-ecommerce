<?php

use App\Http\Controllers\PaymentDistributionController as Payments;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:60,1'])->prefix('payments')->group(function () {
    Route::get('/distributions', [Payments::class, 'index'])->name('payments.distributions');
    Route::post('/distributions/{id}/receipts', [Payments::class, 'receipt'])->whereNumber('id');
    Route::post('/distributions/{id}/cod-matches', [Payments::class, 'matchCod'])->whereNumber('id');
    Route::post('/distributions/{id}/payouts', [Payments::class, 'reserve'])->whereNumber('id');
    Route::post('/distributions/{id}/refunds', [Payments::class, 'refund'])->whereNumber('id');
    Route::post('/payouts/{id}/outcome', [Payments::class, 'payout'])->whereNumber('id');
    Route::post('/profitability', [Payments::class, 'rule']);
    Route::post('/product-costs', [Payments::class, 'cost']);
});
