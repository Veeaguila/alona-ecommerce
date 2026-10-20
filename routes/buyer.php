<?php

use App\Http\Controllers\BuyerAddressController;
use App\Http\Controllers\BuyerCartController;
use App\Http\Controllers\BuyerCategoryController;
use App\Http\Controllers\BuyerConversationController;
use App\Http\Controllers\BuyerDashboardController;
use App\Http\Controllers\BuyerMessageController;
use App\Http\Controllers\BuyerNotificationController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\BuyerOrderConversationController;
use App\Http\Controllers\BuyerPaymentMethodController;
use App\Http\Controllers\BuyerProductController;
use App\Http\Controllers\BuyerProductQuestionController;
use App\Http\Controllers\BuyerReviewController;
use App\Http\Controllers\BuyerStoreController;
use App\Http\Controllers\BuyerSupportController;
use App\Http\Controllers\BuyerVoucherController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC BUYER PAGES
|--------------------------------------------------------------------------
*/

Route::get('/buyer/products', [
    BuyerProductController::class,
    'index',
])->name('buyer.products');

Route::get('/buyer/product/{product}', [
    BuyerProductController::class,
    'show',
])->name('buyer.product');

Route::get('/buyer/store/{seller}', [
    BuyerStoreController::class,
    'show',
])->name('buyer.store.show');

Route::get('/buyer/categories', [
    BuyerCategoryController::class,
    'index',
])->name('buyer.categories');

Route::get('/buyer/category/{category:slug}', [
    BuyerCategoryController::class,
    'show',
])->name('buyer.category.show');


/*
|--------------------------------------------------------------------------
| BUYER DASHBOARD
|--------------------------------------------------------------------------
|
| Uses BuyerDashboardController for:
| - Categories
| - Featured Products
| - Recommended Products
| - Top Selling Products
| - Promotions
| - Recently Viewed Products
|
|--------------------------------------------------------------------------
*/

Route::get('/buyer', BuyerDashboardController::class)
    ->middleware([
        'auth',
        'buyer',
    ])
    ->name('buyer.dashboard');


/*
|--------------------------------------------------------------------------
| BUYER CART
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'buyer',
])->group(function () {

    Route::get('/buyer/cart', [
        BuyerCartController::class,
        'index',
    ])->name('buyer.cart');

    Route::post('/buyer/cart/{product}', [
        BuyerCartController::class,
        'store',
    ])->name('buyer.cart.store');

    Route::patch('/buyer/cart/{cartItem}', [
        BuyerCartController::class,
        'update',
    ])->name('buyer.cart.update');

    Route::delete('/buyer/cart/{cartItem}', [
        BuyerCartController::class,
        'destroy',
    ])->name('buyer.cart.destroy');

});


/*
|--------------------------------------------------------------------------
| BUYER ORDERS & CHECKOUT
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'buyer',
])->group(function () {

    Route::get('/buyer/checkout', [
        BuyerOrderController::class,
        'checkout',
    ])->name('buyer.checkout');

    Route::post('/buyer/vouchers/validate', [
        BuyerOrderController::class,
        'validateVoucher',
    ])->name('buyer.vouchers.validate');

    Route::post('/buyer/orders', [
        BuyerOrderController::class,
        'store',
    ])->name('buyer.orders.store');

    Route::get('/buyer/orders', [
        BuyerOrderController::class,
        'index',
    ])->name('buyer.orders');

    Route::get('/buyer/orders/{order}', [
        BuyerOrderController::class,
        'show',
    ])->name('buyer.orders.show');

    Route::match(['post', 'patch'], '/buyer/orders/{order}/cancel', [
        BuyerOrderController::class,
        'cancel',
    ])->name('buyer.orders.cancel');

    Route::post('/buyer/orders/{order}/reorder', [
        BuyerOrderController::class,
        'reorder',
    ])->name('buyer.orders.reorder');

    Route::post('/buyer/order-items/{orderItem}/reorder', [
        BuyerOrderController::class,
        'reorderItem',
    ])->name('buyer.order-items.reorder');

    Route::patch('/buyer/order-items/{orderItem}/confirm-delivery', [
        BuyerOrderController::class,
        'confirmDelivery',
    ])->name('buyer.order-items.confirm-delivery');

    Route::post('/buyer/order-items/{orderItem}/return', [
        BuyerOrderController::class,
        'storeReturnRequest',
    ])->name('buyer.order-items.return');

    Route::post('/buyer/return-requests/{returnRequest}/cancel', [
        BuyerOrderController::class,
        'cancelReturnRequest',
    ])->name('buyer.return-requests.cancel');

    Route::post('/buyer/return-requests/{returnRequest}/ship', [
        BuyerOrderController::class,
        'shipReturnRequest',
    ])->name('buyer.return-requests.ship');

    Route::post('/buyer/return-requests/{returnRequest}/escalate', [
        BuyerOrderController::class,
        'escalateReturnRequest',
    ])->name('buyer.return-requests.escalate');

});


/*
|--------------------------------------------------------------------------
| BUYER REVIEWS / NOTIFICATIONS / MESSAGES / SUPPORT
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'buyer',
])->group(function () {

    // Buyer Support, Help Center, FAQs, Policies & Complaints (BUYER-32 to BUYER-38)
    Route::get('/buyer/help', [
        BuyerSupportController::class,
        'help',
    ])->name('buyer.help');

    Route::get('/buyer/faq', [
        BuyerSupportController::class,
        'faq',
    ])->name('buyer.faq');

    Route::get('/buyer/policies', [
        BuyerSupportController::class,
        'policies',
    ])->name('buyer.policies');

    Route::get('/buyer/complaints', [
        BuyerSupportController::class,
        'complaints',
    ])->name('buyer.complaints');

    Route::post('/buyer/complaints', [
        BuyerSupportController::class,
        'storeComplaint',
    ])->name('buyer.complaints.store');

    Route::post('/buyer/reports', [
        BuyerSupportController::class,
        'storeReport',
    ])->name('buyer.reports.store');

    Route::post('/buyer/support/contact', [
        BuyerSupportController::class,
        'contactSupport',
    ])->name('buyer.support.contact');

    Route::get('/buyer/reviews', [
        BuyerReviewController::class,
        'index',
    ])->name('buyer.reviews');

    Route::post('/buyer/products/{product}/reviews', [
        BuyerReviewController::class,
        'store',
    ])->name('buyer.reviews.store');

    Route::patch('/buyer/reviews/{review}', [
        BuyerReviewController::class,
        'update',
    ])->name('buyer.reviews.update');

    Route::delete('/buyer/reviews/{review}', [
        BuyerReviewController::class,
        'destroy',
    ])->name('buyer.reviews.destroy');

    Route::post('/buyer/orders/{order}/sellers/{seller}/review', [
        BuyerReviewController::class,
        'storeSellerReview',
    ])->name('buyer.seller-reviews.store');

    Route::get('/buyer/products/{product}/reviews', [
        BuyerReviewController::class,
        'productReviews',
    ])->name('buyer.product.reviews');

    // Buyer Vouchers (BUYER-24, BUYER-25, BUYER-26)
    Route::get('/buyer/vouchers', [
        BuyerVoucherController::class,
        'index',
    ])->name('buyer.vouchers');

    Route::post('/buyer/vouchers/{voucher}/claim', [
        BuyerVoucherController::class,
        'claim',
    ])->name('buyer.vouchers.claim');

    Route::get('/buyer/notifications', [
        BuyerNotificationController::class,
        'index',
    ])->name('buyer.notifications');

    Route::patch('/buyer/notifications/{notification}/read', [
        BuyerNotificationController::class,
        'read',
    ])->name('buyer.notifications.read');

    Route::post('/buyer/notifications/read-all', [
        BuyerNotificationController::class,
        'readAll',
    ])->name('buyer.notifications.read-all');

    Route::get('/buyer/messages', [
        BuyerMessageController::class,
        'index',
    ])->name('buyer.messages');

    Route::post('/buyer/messages', [
        BuyerMessageController::class,
        'store',
    ])->name('buyer.messages.store');

    // Direct buyer <-> seller chat
    Route::get('/buyer/conversations', [
        BuyerConversationController::class,
        'index',
    ])->name('buyer.conversations');

    Route::get('/buyer/conversations/{conversation}', [
        BuyerConversationController::class,
        'show',
    ])->name('buyer.conversations.show');

    Route::post('/buyer/conversations/{conversation}', [
        BuyerConversationController::class,
        'store',
    ])->name('buyer.conversations.store');

    Route::post('/buyer/conversations/start/{product}', [
        BuyerConversationController::class,
        'start',
    ])->name('buyer.conversations.start');

    Route::post('/buyer/order-items/{orderItem}/contact-seller', [
        BuyerOrderConversationController::class,
        'start',
    ])->name('buyer.order-items.contact-seller');

    // Product Q&A
    Route::post('/buyer/products/{product}/questions', [
        BuyerProductQuestionController::class,
        'store',
    ])->name('buyer.products.questions.store');

});


/*
|--------------------------------------------------------------------------
| BUYER ACCOUNT & SESSIONS
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'buyer',
])->group(function () {

    Route::get('/buyer/account', [
        ProfileController::class,
        'edit',
    ])->name('buyer.account');

    Route::patch('/buyer/account', [
        ProfileController::class,
        'update',
    ])->name('buyer.account.update');

    // Login Session Management (BUYER-39)
    Route::delete('/buyer/sessions/{session}', [
        ProfileController::class,
        'revokeSession',
    ])->name('buyer.sessions.revoke');

    Route::post('/buyer/sessions/revoke-other', [
        ProfileController::class,
        'revokeOtherSessions',
    ])->name('buyer.sessions.revoke-other');

});


/*
|--------------------------------------------------------------------------
| BUYER ADDRESSES & PAYMENT METHODS
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'buyer',
])->group(function () {

    Route::get('/buyer/addresses', [
        BuyerAddressController::class,
        'index',
    ])->name('buyer.addresses');

    Route::post('/buyer/addresses', [
        BuyerAddressController::class,
        'store',
    ])->name('buyer.addresses.store');

    Route::patch('/buyer/addresses/{address}', [
        BuyerAddressController::class,
        'update',
    ])->name('buyer.addresses.update');

    Route::delete('/buyer/addresses/{address}', [
        BuyerAddressController::class,
        'destroy',
    ])->name('buyer.addresses.destroy');

    Route::get('/buyer/payment-methods', [
        BuyerPaymentMethodController::class,
        'index',
    ])->name('buyer.payment-methods');

    Route::post('/buyer/payment-methods', [
        BuyerPaymentMethodController::class,
        'store',
    ])->name('buyer.payment-methods.store');

    Route::delete('/buyer/payment-methods/{paymentMethod}', [
        BuyerPaymentMethodController::class,
        'destroy',
    ])->name('buyer.payment-methods.destroy');

});

