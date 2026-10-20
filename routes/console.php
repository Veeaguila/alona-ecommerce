<?php

// Console command route definitions for scheduled or application-specific tasks.

use App\Models\Order;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:auto-cancel-unpaid', function () {
    $count = Order::autoCancelExpiredUnpaidOrders();
    $this->info("Auto-cancelled {$count} expired unpaid order(s).");
})->purpose('Auto-cancel unpaid e-wallet/card orders older than 1 hour and restore stock');

Schedule::command('orders:auto-cancel-unpaid')->everyMinute();
