<?php

use App\Http\Controllers\SellerAccountController;
use App\Http\Controllers\SellerConversationController;
use App\Http\Controllers\SellerCustomerController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerFlashSaleController;
use App\Http\Controllers\SellerNotificationController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerProductQuestionController;
use App\Http\Controllers\SellerPromotionController;
use App\Http\Controllers\SellerReportController;
use App\Http\Controllers\SellerReturnController;
use App\Http\Controllers\SellerReviewController;
use App\Http\Controllers\SellerSaleController;
use App\Http\Controllers\SellerSettingController;
use App\Http\Controllers\SellerStoreCategoryController;
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
    Route::get('/seller/dashboard', [
        SellerDashboardController::class,
        'index',
    ]);


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

    Route::post('/seller/products/bulk-deactivate', [
        SellerProductController::class,
        'bulkDeactivate',
    ])->name('seller.products.bulk-deactivate');

    Route::post('/seller/products/bulk-delete', [
        SellerProductController::class,
        'bulkDelete',
    ])->name('seller.products.bulk-delete');

    Route::post('/seller/products/{product}/duplicate', [
        SellerProductController::class,
        'duplicate',
    ])->name('seller.products.duplicate');

    Route::patch('/seller/products/{product}/status', [
        SellerProductController::class,
        'toggleStatus',
    ])->name('seller.products.status');

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

    Route::match(['post', 'patch'], '/seller/vouchers/{voucher}/end', [
        SellerVoucherController::class,
        'endNow',
    ])->name('seller.vouchers.end');

    Route::delete('/seller/vouchers/{voucher}', [
        SellerVoucherController::class,
        'destroy',
    ])->name('seller.vouchers.destroy');

    /*
    |--------------------------------------------------------------------------
    | SELLER PRODUCT DISCOUNTS & SALES (MODULE S6)
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/sales', [
        SellerVoucherController::class,
        'index',
    ])->name('seller.sales');

    Route::post('/seller/sales', [
        SellerSaleController::class,
        'store',
    ])->name('seller.sales.store');

    Route::patch('/seller/sales/{sale}', [
        SellerSaleController::class,
        'update',
    ])->name('seller.sales.update');

    Route::match(['post', 'patch'], '/seller/sales/{sale}/end', [
        SellerSaleController::class,
        'endNow',
    ])->name('seller.sales.end');

    Route::delete('/seller/sales/{sale}', [
        SellerSaleController::class,
        'destroy',
    ])->name('seller.sales.destroy');


    /*
    |--------------------------------------------------------------------------
    | SELLER FLASH SALES, BUNDLES & PROMOTIONS (MODULE S8)
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/flash-sales', [
        SellerVoucherController::class,
        'index',
    ])->name('seller.flash-sales');

    Route::post('/seller/flash-sales', [
        SellerFlashSaleController::class,
        'store',
    ])->name('seller.flash-sales.store');

    Route::patch('/seller/flash-sales/{flashSale}', [
        SellerFlashSaleController::class,
        'update',
    ])->name('seller.flash-sales.update');

    Route::match(['post', 'patch'], '/seller/flash-sales/{flashSale}/end', [
        SellerFlashSaleController::class,
        'endNow',
    ])->name('seller.flash-sales.end');

    Route::delete('/seller/flash-sales/{flashSale}', [
        SellerFlashSaleController::class,
        'destroy',
    ])->name('seller.flash-sales.destroy');

    Route::get('/seller/promotions', [
        SellerVoucherController::class,
        'index',
    ])->name('seller.promotions');

    Route::post('/seller/promotions', [
        SellerPromotionController::class,
        'store',
    ])->name('seller.promotions.store');

    Route::post('/seller/bundles', [
        SellerPromotionController::class,
        'store',
    ])->name('seller.bundles.store');

    Route::match(['post', 'patch'], '/seller/promotions/{promotion}/end', [
        SellerPromotionController::class,
        'endNow',
    ])->name('seller.promotions.end');

    Route::delete('/seller/promotions/{promotion}', [
        SellerPromotionController::class,
        'destroy',
    ])->name('seller.promotions.destroy');


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

    Route::get('/seller/orders/{orderItem}/delivery-proofs/{proof}', [
        \App\Http\Controllers\SellerDeliveryProofController::class, 'file',
    ])->name('seller.delivery-proofs.file');

    Route::patch('/seller/orders/{orderItem}/process', [
        SellerOrderController::class,
        'process',
    ])->name('seller.orders.process');

    Route::patch('/seller/orders/{orderItem}/accept', [
        SellerOrderController::class,
        'process',
    ])->name('seller.orders.accept');

    Route::patch('/seller/orders/{orderItem}/pack', [
        SellerOrderController::class,
        'pack',
    ])->name('seller.orders.pack');

    Route::patch('/seller/orders/{orderItem}/ready-for-pickup', [
        SellerOrderController::class,
        'readyForPickup',
    ])->name('seller.orders.ready-for-pickup');

    Route::post('/seller/orders/{orderItem}/schedule-pickup', [
        SellerOrderController::class,
        'schedulePickup',
    ])->name('seller.orders.schedule-pickup');

    Route::get('/seller/orders/{orderItem}/waybill', [
        SellerOrderController::class,
        'waybill',
    ])->name('seller.orders.waybill');

    Route::get('/seller/orders/{orderItem}/shipping-documents', [
        SellerOrderController::class,
        'shippingDocuments',
    ])->name('seller.orders.shipping-documents');

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
    | SELLER RETURNS & REFUNDS (SELLER-36 to SELLER-41)
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/returns', [
        SellerReturnController::class,
        'index',
    ])->name('seller.returns');

    Route::get('/seller/returns/{returnRequest}', [
        SellerReturnController::class,
        'show',
    ])->name('seller.returns.show');

    Route::match(['post', 'patch'], '/seller/returns/{returnRequest}/decision', [
        SellerReturnController::class,
        'decision',
    ])->name('seller.returns.decision');

    Route::post('/seller/returns/{returnRequest}/approve', [
        SellerReturnController::class,
        'decision',
    ])->name('seller.returns.approve');

    Route::post('/seller/returns/{returnRequest}/reject', [
        SellerReturnController::class,
        'decision',
    ])->name('seller.returns.reject');

    Route::post('/seller/returns/{returnRequest}/tracking', [
        SellerReturnController::class,
        'updateTracking',
    ])->name('seller.returns.tracking');

    Route::post('/seller/returns/{returnRequest}/inspect-resolve', [
        SellerReturnController::class,
        'inspectAndResolve',
    ])->name('seller.returns.inspect-resolve');


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

    Route::post('/seller/messages/support', [
        SellerConversationController::class,
        'startAdminSupport',
    ])->name('seller.messages.support');

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

    Route::post('/seller/reviews/{review}/report', [
        SellerReviewController::class,
        'report',
    ])->name('seller.reviews.report');

    Route::post('/seller/store-reviews/{sellerReview}/reply', [
        SellerReviewController::class,
        'replyStoreReview',
    ])->name('seller.store-reviews.reply');

    Route::post('/seller/store-reviews/{sellerReview}/report', [
        SellerReviewController::class,
        'reportStoreReview',
    ])->name('seller.store-reviews.report');


    /*
    |--------------------------------------------------------------------------
    | SELLER STORE PROFILE
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/store/{seller?}', [
        SellerStoreController::class,
        'edit',
    ])->name('seller.store');

    Route::patch('/seller/store/{seller?}', [
        SellerStoreController::class,
        'update',
    ])->name('seller.store.update');

    /*
    |--------------------------------------------------------------------------
    | SELLER STORE CATEGORIES (Module S2: SEL-SCAT-001 - SEL-SCAT-013)
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/categories', [
        SellerStoreCategoryController::class,
        'index',
    ])->name('seller.categories');

    Route::post('/seller/categories', [
        SellerStoreCategoryController::class,
        'store',
    ])->name('seller.categories.store');

    Route::patch('/seller/categories/reorder', [
        SellerStoreCategoryController::class,
        'reorder',
    ])->name('seller.categories.reorder');

    Route::patch('/seller/categories/{storeCategory}', [
        SellerStoreCategoryController::class,
        'update',
    ])->name('seller.categories.update');

    Route::delete('/seller/categories/{storeCategory}', [
        SellerStoreCategoryController::class,
        'destroy',
    ])->name('seller.categories.destroy');

    Route::post('/seller/products/{product}/store-categories', [
        SellerStoreCategoryController::class,
        'assignProductCategories',
    ])->name('seller.products.store-categories.assign');

    Route::post('/seller/categories/{storeCategory}/products', [
        SellerStoreCategoryController::class,
        'addProducts',
    ])->name('seller.categories.products.add');

    Route::delete('/seller/categories/{storeCategory}/products/{product}', [
        SellerStoreCategoryController::class,
        'removeProduct',
    ])->name('seller.categories.products.remove');


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

    Route::post('/seller/payout-requests', [
        SellerNotificationController::class,
        'storePayoutRequest',
    ])->name('seller.payout-requests.store');


    /*
    |--------------------------------------------------------------------------
    | SELLER ACCOUNT & SECURITY
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/account', [
        SellerAccountController::class,
        'edit',
    ])->name('seller.account');

    Route::post('/seller/account/profile', [
        SellerAccountController::class,
        'updateProfile',
    ])->name('seller.account.profile.update');

    Route::patch('/seller/account/profile', [
        SellerAccountController::class,
        'updateProfile',
    ]);

    Route::post('/seller/account/documents', [
        SellerAccountController::class,
        'updateDocuments',
    ])->name('seller.account.documents.update');

    Route::put('/seller/account/password', [
        SellerAccountController::class,
        'updatePassword',
    ])->name('seller.account.password.update');

    Route::delete('/seller/account/sessions/{session}', [
        SellerAccountController::class,
        'revokeSession',
    ])->name('seller.account.sessions.revoke');

    Route::post('/seller/account/sessions/revoke-other', [
        SellerAccountController::class,
        'revokeOtherSessions',
    ])->name('seller.account.sessions.revoke-other');

    Route::delete('/seller/account', [
        SellerAccountController::class,
        'destroy',
    ])->name('seller.account.destroy');

});

