<?php

use App\Http\Controllers\SellerConversationController;
use App\Http\Controllers\SellerCustomerController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerNotificationController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerProductQuestionController;
use App\Http\Controllers\SellerReportController;
use App\Http\Controllers\SellerReviewController;
use App\Http\Controllers\SellerSettingController;
use App\Http\Controllers\SellerStoreController;
use App\Http\Controllers\SellerVoucherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SELLER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'seller',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | SELLER DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/seller', [
        SellerDashboardController::class,
        'index',
    ])->name('seller.dashboard');


    /*
    |--------------------------------------------------------------------------
    | SELLER INVENTORY / PRODUCTS
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/products', [
        SellerProductController::class,
        'index',
    ])->name('seller.products');

    Route::get('/seller/products/create', [
        SellerProductController::class,
        'create',
    ])->name('seller.products.create');

    Route::post('/seller/products', [
        SellerProductController::class,
        'store',
    ])->name('seller.products.store');

    Route::get('/seller/products/{product}/edit', [
        SellerProductController::class,
        'edit',
    ])->name('seller.products.edit');

    Route::patch('/seller/products/{product}', [
        SellerProductController::class,
        'update',
    ])->name('seller.products.update');

    Route::delete('/seller/products/{product}', [
        SellerProductController::class,
        'destroy',
    ])->name('seller.products.destroy');

    Route::patch('/seller/products/{product}/archive', [
        SellerProductController::class,
        'archive',
    ])->name('seller.products.archive');

    Route::patch('/seller/products/{product}/restore', [
        SellerProductController::class,
        'restore',
    ])->name('seller.products.restore');


    /*
    |--------------------------------------------------------------------------
    | INVENTORY STOCK MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/products/{product}/inventory', [
        SellerProductController::class,
        'inventory',
    ])->name('seller.products.inventory');

    Route::patch('/seller/products/{product}/inventory', [
        SellerProductController::class,
        'updateInventory',
    ])->name('seller.products.inventory.update');

    Route::post('/seller/products/{product}/adjust-stock', [
        SellerProductController::class,
        'adjustStock',
    ])->name('seller.products.adjust-stock');


    /*
    |--------------------------------------------------------------------------
    | STOCK MONITORING
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/stock-monitoring', [
        SellerProductController::class,
        'stockMonitoring',
    ])->name('seller.stock-monitoring');


    /*
    |--------------------------------------------------------------------------
    | PRODUCT VARIANT MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::post('/seller/products/{product}/variants', [
        SellerProductController::class,
        'storeVariant',
    ])->name('seller.products.variants.store');

    Route::patch('/seller/products/{product}/variants/{variant}', [
        SellerProductController::class,
        'updateVariant',
    ])->name('seller.products.variants.update');

    Route::delete('/seller/products/{product}/variants/{variant}', [
        SellerProductController::class,
        'destroyVariant',
    ])->name('seller.products.variants.destroy');


    /*
    |--------------------------------------------------------------------------
    | PRODUCT QUESTIONS & ANSWERS
    |--------------------------------------------------------------------------
    */

    Route::post('/seller/questions/{question}/answer', [
        SellerProductQuestionController::class,
        'answer',
    ])->name('seller.questions.answer');


    /*
    |--------------------------------------------------------------------------
    | SELLER VOUCHERS
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/vouchers', [
        SellerVoucherController::class,
        'index',
    ])->name('seller.vouchers');

    Route::post('/seller/vouchers', [
        SellerVoucherController::class,
        'store',
    ])->name('seller.vouchers.store');

    Route::patch('/seller/vouchers/{voucher}', [
        SellerVoucherController::class,
        'update',
    ])->name('seller.vouchers.update');

    Route::delete('/seller/vouchers/{voucher}', [
        SellerVoucherController::class,
        'destroy',
    ])->name('seller.vouchers.destroy');


    /*
    |--------------------------------------------------------------------------
    | SELLER ORDER MANAGEMENT & FULFILLMENT
    |--------------------------------------------------------------------------
    |
    | Seller workflow:
    | Pending → Processing → Packed → Ready for Pickup
    |
    | After Ready for Pickup:
    | Logistics / Courier handles the shipment.
    |
    */

    Route::get('/seller/orders', [
        SellerOrderController::class,
        'index',
    ])->name('seller.orders');

    Route::get('/seller/orders/{orderItem}', [
        SellerOrderController::class,
        'show',
    ])->name('seller.order.details');

    Route::patch('/seller/orders/{orderItem}/process', [
        SellerOrderController::class,
        'process',
    ])->name('seller.orders.process');

    Route::patch('/seller/orders/{orderItem}/pack', [
        SellerOrderController::class,
        'pack',
    ])->name('seller.orders.pack');

    Route::patch('/seller/orders/{orderItem}/ready-for-pickup', [
        SellerOrderController::class,
        'readyForPickup',
    ])->name('seller.orders.ready-for-pickup');

    Route::patch('/seller/orders/{orderItem}/cancel', [
        SellerOrderController::class,
        'cancel',
    ])->name('seller.orders.cancel');

    Route::post('/seller/orders/{orderItem}/note', [
        SellerOrderController::class,
        'note',
    ])->name('seller.orders.note');


    /*
    |--------------------------------------------------------------------------
    | SELLER REPORTS
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/reports', [
        SellerReportController::class,
        'reports',
    ])->name('seller.reports');


    /*
    |--------------------------------------------------------------------------
    | SELLER CUSTOMERS
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/customers', [
        SellerCustomerController::class,
        'index',
    ])->name('seller.customers');


    /*
    |--------------------------------------------------------------------------
    | SELLER CHAT / MESSAGES
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/messages', [
        SellerConversationController::class,
        'index',
    ])->name('seller.messages');

    Route::get('/seller/messages/{conversation}', [
        SellerConversationController::class,
        'show',
    ])->name('seller.messages.show');

    Route::post('/seller/messages/{conversation}', [
        SellerConversationController::class,
        'store',
    ])->name('seller.messages.store');

    Route::post('/seller/messages/start/{buyer}', [
        SellerConversationController::class,
        'start',
    ])->name('seller.messages.start');


    /*
    |--------------------------------------------------------------------------
    | SELLER CUSTOMER FEEDBACK
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/reviews', [
        SellerReviewController::class,
        'index',
    ])->name('seller.reviews');

    Route::post('/seller/reviews/{review}/reply', [
        SellerReviewController::class,
        'reply',
    ])->name('seller.reviews.reply');


    /*
    |--------------------------------------------------------------------------
    | SELLER STORE PROFILE
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/store', [
        SellerStoreController::class,
        'edit',
    ])->name('seller.store');

    Route::patch('/seller/store', [
        SellerStoreController::class,
        'update',
    ])->name('seller.store.update');


    /*
    |--------------------------------------------------------------------------
    | SELLER SETTINGS
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/settings', [
        SellerSettingController::class,
        'edit',
    ])->name('seller.settings');

    Route::patch('/seller/settings', [
        SellerSettingController::class,
        'update',
    ])->name('seller.settings.update');


    /*
    |--------------------------------------------------------------------------
    | SELLER NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/notifications', [
        SellerNotificationController::class,
        'index',
    ])->name('seller.notifications');

    Route::patch('/seller/notifications/{notification}/read', [
        SellerNotificationController::class,
        'read',
    ])->name('seller.notifications.read');

    Route::post('/seller/notifications/read-all', [
        SellerNotificationController::class,
        'readAll',
    ])->name('seller.notifications.read-all');


    /*
    |--------------------------------------------------------------------------
    | SELLER ACCOUNT
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/account', function () {
        return redirect()->route('profile.edit');
    })->name('seller.account');

});

