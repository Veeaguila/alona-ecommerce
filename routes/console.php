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

Artisan::command('orders:auto-complete-delivered', function () {
    $count = Order::autoCompleteDeliveredOrders();
    $this->info("Auto-completed {$count} delivered order(s).");
})->purpose('Auto-complete orders delivered for more than 7 days');

Schedule::command('orders:auto-complete-delivered')->daily();

Artisan::command('flash-sales:send-reminders', function () {
    $count = \App\Services\FlashSaleReminderService::sendDueReminders();
    $this->info("Dispatched {$count} flash sale reminder notification(s).");
})->purpose('Send reminder notifications before flash sale slots start');

Schedule::command('flash-sales:send-reminders')->everyMinute();

Artisan::command('logistics:advance-pickup-offers', function () {
    if (!\Illuminate\Support\Facades\Schema::hasTable('pickup_offers')) return;
    $ids = \App\Models\PickupOffer::where('kind', 'nearest')->where('status', 'offered')->distinct()->pluck('sorting_center_id');
    foreach ($ids as $id) app(\App\Services\NearestPickupOffer::class)->advanceForCenter((int) $id);
    $this->info('Nearest pickup offers checked.');
})->purpose('Advance expired nearest pickup offers to the next eligible Rider, up to three Riders');
Schedule::command('logistics:advance-pickup-offers')->everyMinute()->withoutOverlapping();
