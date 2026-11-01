<?php

use App\Http\Controllers\Api\Buyer\BuyerAccountApiController;
use App\Http\Controllers\Api\Buyer\BuyerAuthController;
use App\Http\Controllers\Api\Buyer\BuyerCartApiController;
use App\Http\Controllers\Api\Buyer\BuyerCatalogController;
use App\Http\Controllers\Api\Buyer\BuyerOrderApiController;
use App\Http\Controllers\Api\Buyer\BuyerVoucherApiController;
use App\Http\Controllers\DeliveryCoverageController;
use App\Http\Controllers\Logistics\DashboardController;
use App\Http\Controllers\Logistics\DeliveryProofController;
use App\Http\Controllers\Logistics\IncomingParcelController;
use App\Http\Controllers\Logistics\LoginRiderController;
use App\Http\Controllers\Logistics\LoginSortingCenterController;
use App\Http\Controllers\Logistics\MessageController;
use App\Http\Controllers\Logistics\ParcelAssignmentController;
use App\Http\Controllers\Logistics\ParcelMonitoringController;
use App\Http\Controllers\Logistics\ParcelSortingController;
use App\Http\Controllers\Logistics\PickupRequestController;
use App\Http\Controllers\Logistics\ReportController;
use App\Http\Controllers\Logistics\RiderDeliveryController;
use App\Http\Controllers\Logistics\RiderManagementController;
use App\Http\Controllers\Logistics\RiderPickupController;
use App\Http\Controllers\Logistics\SellerPickupRequestController;
use App\Http\Controllers\Logistics\SortingCenterDirectoryController;
use App\Http\Controllers\PartnerProfileController;
use App\Http\Controllers\PartnerRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Sorting Center Auth
Route::get('/sorting-centers', [SortingCenterDirectoryController::class, 'index']);
Route::post('/sorting-center/register', [PartnerRegistrationController::class, 'store'])->middleware('throttle:6,1');
Route::post('/sorting-center/onboarding/register', [\App\Http\Controllers\LogisticsRegistrationController::class, 'store'])->middleware('throttle:6,1');
Route::post('/sorting-center/login', [LoginSortingCenterController::class, 'login'])->middleware('throttle:6,1');

// Public Rider Registration (for seeding & future Flutter Rider App)
Route::post('/rider/register', [PartnerRegistrationController::class, 'store'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('hub.operator')->prefix('sorting-center/hub-workspace')->group(function () {
        Route::get('/', [\App\Http\Controllers\Logistics\HubWorkspaceController::class, 'index']);
        Route::get('/queue', [\App\Http\Controllers\Logistics\HubWorkspaceController::class, 'queue']);
        Route::get('/lookup', [\App\Http\Controllers\Logistics\HubWorkspaceController::class, 'lookup'])->middleware('throttle:60,1');
        Route::post('/actions', [\App\Http\Controllers\Logistics\HubWorkspaceController::class, 'act'])->middleware('throttle:30,1');
    });
    Route::middleware('sorting_center.approved')->prefix('sorting-center/hub-staff')->group(function () {
        Route::get('/', [\App\Http\Controllers\Logistics\HubStaffController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Logistics\HubStaffController::class, 'store'])->middleware('throttle:10,1');
        Route::put('/{id}', [\App\Http\Controllers\Logistics\HubStaffController::class, 'update'])->whereNumber('id');
    });
    Route::get('/partner/profile', [PartnerProfileController::class, 'status']);
    Route::post('/sorting-center/onboarding/resend-link', [\App\Http\Controllers\Auth\EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')->name('api.logistics.verification.resend');
    Route::post('/sorting-center/onboarding/verify-email', [\App\Http\Controllers\LogisticsRegistrationController::class, 'verify'])->middleware('throttle:6,1');
    Route::post('/sorting-center/onboarding/resend-code', [\App\Http\Controllers\LogisticsRegistrationController::class, 'resend'])->middleware('throttle:3,1');
    Route::post('/sorting-center/onboarding/resubmit', [\App\Http\Controllers\LogisticsRegistrationController::class, 'resubmit'])->middleware('throttle:6,1');
    Route::patch('/partner/profile', [PartnerProfileController::class, 'update']);
    Route::put('/partner/password', [\App\Http\Controllers\Auth\PasswordController::class, 'update'])->middleware('throttle:6,1');
    Route::patch('/rider/profile', [\App\Http\Controllers\Logistics\RiderProfileController::class, 'update']);
    Route::get('/rider/profile-photo', [\App\Http\Controllers\Logistics\RiderProfileController::class, 'photo']);
    Route::post('/rider/profile-photo', [\App\Http\Controllers\Logistics\RiderProfileController::class, 'uploadPhoto'])->middleware('throttle:20,1');
    // Own notifications remain readable while approval is pending/rejected.
    Route::get('/rider/notifications', [\App\Http\Controllers\Logistics\RiderNotificationController::class, 'index']);
    // Suspended custodians may acknowledge an existing physical handover; this grants no operational job access.
    Route::get('/rider/handovers', [\App\Http\Controllers\Logistics\ParcelHandoverController::class, 'mine']);
    Route::get('/rider/cod', [\App\Http\Controllers\Logistics\CodController::class, 'mine']);
    Route::post('/rider/cod/collections/{id}', [\App\Http\Controllers\Logistics\CodController::class, 'collect'])->whereNumber('id')->middleware('throttle:20,1');
    Route::post('/rider/cod/remittances', [\App\Http\Controllers\Logistics\CodController::class, 'submit'])->middleware('throttle:20,1');
    Route::get('/cod/evidence/{kind}/{id}', [\App\Http\Controllers\Logistics\CodController::class, 'evidence'])->whereNumber('id');
    Route::post('/rider/handovers/{id}/acknowledge', [\App\Http\Controllers\Logistics\ParcelHandoverController::class, 'acknowledge'])->whereNumber('id')->middleware('throttle:20,1');
    Route::post('/rider/notifications/{id}/read', [\App\Http\Controllers\Logistics\RiderNotificationController::class, 'read']);
    Route::get('/user', fn (Request $request) => $request->user()->only(['id', 'name', 'email', 'usertype', 'status']));
});
Route::post('/rider/login', [LoginRiderController::class, 'login'])->middleware('throttle:6,1');
Route::get('/rider/activate/{token}', [\App\Http\Controllers\RiderActivationController::class, 'show']);
Route::post('/rider/activate/{token}', [\App\Http\Controllers\RiderActivationController::class, 'activate']);

Route::middleware(['auth:sanctum', 'rider.approved'])->prefix('rider')->group(function () {
    Route::post('/pickup-location', [\App\Http\Controllers\Logistics\PickupLocationController::class, 'rider'])->middleware('throttle:30,1');
    Route::delete('/pickup-location', [\App\Http\Controllers\Logistics\PickupLocationController::class, 'rider']);
    Route::get('/presence', [\App\Http\Controllers\Logistics\RiderPresenceController::class, 'show']);
    Route::post('/presence', [\App\Http\Controllers\Logistics\RiderPresenceController::class, 'update'])->middleware('throttle:120,1');
    Route::get('/earnings', [\App\Http\Controllers\DeliveryFinanceController::class, 'rider']);
    Route::get('/job-pay', [\App\Http\Controllers\Logistics\RiderPayController::class, 'index']);
    Route::post('/job-pay/destinations', [\App\Http\Controllers\Logistics\RiderPayController::class, 'destination'])->middleware('throttle:10,1');
    Route::get('/job-pay/payouts/{id}', [\App\Http\Controllers\Logistics\RiderPayController::class, 'show'])->whereNumber('id');
    Route::get('/job-pay/payouts/{id}/proof', [\App\Http\Controllers\Logistics\RiderPayController::class, 'proof'])->whereNumber('id');
    Route::post('/job-pay/payouts/{id}/acknowledge', [\App\Http\Controllers\Logistics\RiderPayController::class, 'acknowledge'])->whereNumber('id')->middleware('throttle:20,1');
    Route::get('/pickups/available', [RiderPickupController::class, 'available']);
    Route::get('/pickups/mine', [RiderPickupController::class, 'mine']);
    Route::post('/pickups/{parcelId}/accept', [RiderPickupController::class, 'accept']);
    Route::post('/pickups/{id}/decline', [\App\Http\Controllers\Logistics\PickupDispatchController::class, 'decline'])->whereNumber('id');
    Route::post('/pickups/{id}/acknowledge', [\App\Http\Controllers\Logistics\PickupDispatchController::class, 'acknowledge'])->whereNumber('id');
    Route::post('/pickups/{parcelId}/confirm', [RiderPickupController::class, 'confirm']);
    Route::get('/deliveries', [RiderDeliveryController::class, 'index']);
    Route::get('/deliveries/lookup', [RiderDeliveryController::class, 'lookup'])->middleware('throttle:60,1');
    Route::post('/deliveries/{parcelId}/start', [RiderDeliveryController::class, 'start']);
    Route::post('/deliveries/{parcelId}/deliver', [RiderDeliveryController::class, 'deliver']);
    Route::post('/deliveries/{parcelId}/upload-proof', [RiderDeliveryController::class, 'uploadProof']);
    Route::post('/deliveries/{parcelId}/fail', [RiderDeliveryController::class, 'fail']);
    Route::post('/deliveries/{parcelId}/retry', [RiderDeliveryController::class, 'retry']);
    Route::get('/returns', [\App\Http\Controllers\Logistics\ReturnToSellerController::class, 'mine']);
    Route::post('/returns/{id}/pickup', [\App\Http\Controllers\Logistics\ReturnToSellerController::class, 'pickup'])->whereNumber('id');
    Route::post('/returns/{id}/handover', [\App\Http\Controllers\Logistics\ReturnToSellerController::class, 'handover'])->whereNumber('id');
    Route::get('/messages/recipients', [MessageController::class, 'recipients']);
    Route::get('/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/messages/conversations/{conversationId}', [MessageController::class, 'show']);
    Route::post('/messages', [MessageController::class, 'send']);
    Route::post('/messages/conversations/{conversationId}', [MessageController::class, 'send']);
});

// Protected Sorting Center Management
Route::middleware(['auth:sanctum', 'sorting_center.approved'])->group(function () {
    Route::prefix('sorting-center/payroll')->group(function () {
        $pay = \App\Http\Controllers\Logistics\RiderPayController::class;
        Route::get('/', [$pay, 'index']);
        Route::put('/settings', [$pay, 'settings'])->middleware('throttle:10,1');
        Route::get('/riders/{id}/review', [$pay, 'review'])->whereNumber('id');
        Route::post('/payouts', [$pay, 'prepare'])->middleware('throttle:20,1');
        Route::get('/payouts/{id}', [$pay, 'show'])->whereNumber('id');
        Route::get('/payouts/{id}/proof', [$pay, 'proof'])->whereNumber('id');
        Route::post('/payouts/{id}/pay', [$pay, 'pay'])->whereNumber('id')->middleware('throttle:10,1');
        Route::post('/payouts/{id}/cancel', [$pay, 'cancel'])->whereNumber('id')->middleware('throttle:10,1');
        Route::get('/trips', [$pay, 'trips']);
        Route::post('/trips', [$pay, 'dispatchTrip'])->middleware('throttle:20,1');
    });
    Route::get('/sorting-center/delivery-rates', [\App\Http\Controllers\Logistics\DeliveryRateController::class, 'index']);
    Route::post('/sorting-center/delivery-rates', [\App\Http\Controllers\Logistics\DeliveryRateController::class, 'save']);
    Route::put('/sorting-center/delivery-rates/{id}', [\App\Http\Controllers\Logistics\DeliveryRateController::class, 'save'])->whereNumber('id');
    Route::get('/sorting-center/hub-network', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'index']);
    Route::post('/sorting-center/hubs', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'saveHub']);
    Route::put('/sorting-center/hubs/{id}', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'saveHub'])->whereNumber('id');
    Route::post('/sorting-center/hub-routes', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'saveRoute']);
    Route::put('/sorting-center/hub-routes/{id}', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'saveRoute'])->whereNumber('id');
    Route::put('/sorting-center/riders/{id}/hub', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'assignRider'])->whereNumber('id');
    Route::get('/sorting-center/hub-journeys', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'journeys']);
    Route::post('/sorting-center/hub-journeys/{id}/dispatch', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'dispatch'])->whereNumber('id');
    Route::post('/sorting-center/hub-journeys/{id}/receive', [\App\Http\Controllers\Logistics\HubNetworkController::class, 'receive'])->whereNumber('id');
    Route::post('/sorting-center/coverage', [DeliveryCoverageController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/sorting-center/messages/recipients', [MessageController::class, 'recipients']);
    Route::get('/sorting-center/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/sorting-center/messages/conversations/{conversationId}', [MessageController::class, 'show']);
    Route::post('/sorting-center/messages', [MessageController::class, 'send']);
    Route::post('/sorting-center/messages/conversations/{conversationId}', [MessageController::class, 'send']);
    Route::get('/sorting-center/riders', [RiderManagementController::class, 'index']);
    Route::get('/sorting-center/rider-documents/{user}/{document}', [PartnerProfileController::class, 'document'])
        ->whereNumber('user')->whereIn('document', ['id_doc', 'license_doc', 'or_cr_doc']);
    Route::get('/sorting-center/riders/{id}/delivery-areas', [\App\Http\Controllers\Logistics\RiderCoverageController::class, 'show'])->whereNumber('id');
    Route::put('/sorting-center/riders/{id}/delivery-areas', [\App\Http\Controllers\Logistics\RiderCoverageController::class, 'update'])->whereNumber('id');
    Route::post('/sorting-center/riders', [RiderManagementController::class, 'store']);
    Route::post('/sorting-center/riders/{id}/resend-activation', [RiderManagementController::class, 'resendActivation']);
    Route::get('/sorting-center/riders/{id}/audits', [RiderManagementController::class, 'audits'])->whereNumber('id');
    Route::post('/sorting-center/riders/{id}/approve', [RiderManagementController::class, 'approve']);
    Route::post('/sorting-center/riders/{id}/reject', [RiderManagementController::class, 'reject']);
    Route::post('/sorting-center/riders/{id}/activate', [RiderManagementController::class, 'activate']);
    Route::post('/sorting-center/riders/{id}/deactivate', [RiderManagementController::class, 'deactivate']);
    Route::get('/sorting-center/pickup-requests', [PickupRequestController::class, 'index']);
    Route::get('/sorting-center/returns', [\App\Http\Controllers\Logistics\ReturnToSellerController::class, 'index']);
    Route::post('/sorting-center/returns/{id}/initiate', [\App\Http\Controllers\Logistics\ReturnToSellerController::class, 'initiate'])->whereNumber('id');
    Route::get('/sorting-center/pickup-dispatch/riders', [\App\Http\Controllers\Logistics\PickupDispatchController::class, 'riders']);
    Route::post('/sorting-center/pickup-dispatch/{id}/nearest', [\App\Http\Controllers\Logistics\PickupDispatchController::class, 'nearest'])->whereNumber('id')->middleware('throttle:20,1');
    Route::post('/sorting-center/pickup-dispatch/bulk', [\App\Http\Controllers\Logistics\PickupDispatchController::class, 'bulk'])->middleware('throttle:20,1');
    Route::post('/sorting-center/pickup-dispatch/{id}/assign', [\App\Http\Controllers\Logistics\PickupDispatchController::class, 'assign'])->whereNumber('id');
    Route::post('/sorting-center/pickup-requests/{id}/confirm', [PickupRequestController::class, 'confirm']);
    Route::post('/sorting-center/parcels/mark-arrived', [IncomingParcelController::class, 'markArrived']);
    Route::get('/sorting-center/parcels/lookup/{orderId}', [IncomingParcelController::class, 'lookup']);
    Route::get('/sorting-center/outbound/lookup/{reference}', [\App\Http\Controllers\Logistics\OutboundParcelController::class, 'lookup']);
    Route::post('/sorting-center/outbound/confirm', [\App\Http\Controllers\Logistics\OutboundParcelController::class, 'confirm']);
    Route::get('/sorting-center/parcels', [ParcelSortingController::class, 'index']);
    Route::get('/sorting-center/areas', [ParcelSortingController::class, 'areas']);
    Route::post('/sorting-center/areas', [ParcelSortingController::class, 'createArea']);
    Route::post('/sorting-center/parcels/{id}/sort', [ParcelSortingController::class, 'sort']);
    Route::get('/sorting-center/parcels/{id}/eligible-riders', [ParcelAssignmentController::class, 'eligibleRiders']);
    Route::post('/sorting-center/parcels/{id}/assign', [ParcelAssignmentController::class, 'assign']);
    Route::get('/sorting-center/parcels/monitoring', [ParcelMonitoringController::class, 'index']);
    Route::get('/sorting-center/parcels/{id}/operations', [\App\Http\Controllers\Logistics\ParcelOperationsController::class, 'show'])->whereNumber('id');
    Route::get('/sorting-center/parcels/{id}/handovers', [\App\Http\Controllers\Logistics\ParcelHandoverController::class, 'index'])->whereNumber('id');
    Route::post('/sorting-center/parcels/{id}/handovers', [\App\Http\Controllers\Logistics\ParcelHandoverController::class, 'store'])->whereNumber('id');
    Route::post('/sorting-center/handovers/{id}/confirm', [\App\Http\Controllers\Logistics\ParcelHandoverController::class, 'confirm'])->whereNumber('id');
    Route::post('/sorting-center/handovers/{id}/cancel', [\App\Http\Controllers\Logistics\ParcelHandoverController::class, 'cancel'])->whereNumber('id');
    Route::post('/sorting-center/parcels/{id}/override-delivered', [\App\Http\Controllers\Logistics\ParcelOperationsController::class, 'overrideDelivered'])->whereNumber('id')->middleware('throttle:20,1');
    Route::post('/sorting-center/parcels/{id}/incidents', [\App\Http\Controllers\Logistics\ParcelOperationsController::class, 'incident'])->whereNumber('id')->middleware('throttle:20,1');
    Route::get('/sorting-center/parcels/{id}/incidents/{incidentId}/evidence', [\App\Http\Controllers\Logistics\ParcelOperationsController::class, 'evidence'])->whereNumber(['id', 'incidentId']);
    Route::get('/sorting-center/dashboard', [DashboardController::class, 'index']);
    Route::get('/sorting-center/dashboard/parcels', [DashboardController::class, 'parcels']);
    Route::get('/sorting-center/reports', [ReportController::class, 'index']);
    Route::get('/sorting-center/cod', [\App\Http\Controllers\Logistics\CodController::class, 'report']);
    Route::get('/sorting-center/cod/export', [\App\Http\Controllers\Logistics\CodController::class, 'export']);
    Route::post('/sorting-center/cod/remittances/{id}/confirm', [\App\Http\Controllers\Logistics\CodController::class, 'confirm'])->whereNumber('id')->middleware('throttle:20,1');
    Route::get('/sorting-center/reports/export', [ReportController::class, 'export']);
    Route::get('/sorting-center/reports/delivery-stats', [ReportController::class, 'deliveryStats']);
    Route::get('/sorting-center/reports/transactions', [ReportController::class, 'transactions']);
    Route::get('/sorting-center/parcels/{parcelId}/proofs', [DeliveryProofController::class, 'getProofs']);
    Route::get('/sorting-center/proofs/{proofId}', [DeliveryProofController::class, 'getProof']);
    Route::get('/sorting-center/proofs/{proofId}/file', [DeliveryProofController::class, 'file'])
        ->name('sorting-center.proofs.file');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/logistics-claims', [\App\Http\Controllers\Logistics\LogisticsClaimController::class, 'index']);
    Route::get('/logistics-claims/{id}/evidence', [\App\Http\Controllers\Logistics\LogisticsClaimController::class, 'evidence'])->whereNumber('id');
    Route::get('/cod/discrepancies', [\App\Http\Controllers\Logistics\CodController::class, 'disputes']);
    Route::post('/cod/discrepancies/{id}/approve', [\App\Http\Controllers\Logistics\CodController::class, 'approve'])->whereNumber('id')->middleware('throttle:20,1');
});

// Seller API Routes
Route::middleware(['auth:sanctum', 'seller'])->prefix('seller')->group(function () {
    Route::get('/delivery-charges', [\App\Http\Controllers\DeliveryFinanceController::class, 'seller']);
    Route::post('/pickup-requests', [SellerPickupRequestController::class, 'store']);
});

Route::post('/logistics/logout', function (Request $request) {
    if ($request->user()->rider) app(\App\Services\RiderPresence::class)->offline($request->user()->rider);
    $request->user()->currentAccessToken()?->delete();

    return response()->json(['message' => 'Signed out.']);
})->middleware('auth:sanctum');

// Payment Webhook (BUY-PAY-005)
Route::post('/webhooks/payment', [\App\Http\Controllers\BuyerOrderController::class, 'paymentWebhook'])->name('payment.webhook');

/*
|--------------------------------------------------------------------------
| BUYER MOBILE API ROUTES
|--------------------------------------------------------------------------
*/

// Marketplace Catalog (Public / Guest)
Route::prefix('marketplace')->group(function () {
    Route::get('/home', [BuyerCatalogController::class, 'home']);
    Route::get('/products', [BuyerCatalogController::class, 'products']);
    Route::get('/products/{id}', [BuyerCatalogController::class, 'show']);
    Route::get('/categories', [BuyerCatalogController::class, 'categories']);
});

// Buyer Public Auth
Route::prefix('buyer')->group(function () {
    Route::post('/login', [BuyerAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [BuyerAuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/verify-otp', [BuyerAuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('/resend-otp', [BuyerAuthController::class, 'resendOtp'])->middleware('throttle:5,1');
    Route::post('/google-login', [BuyerAuthController::class, 'googleLogin'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [BuyerAuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
});

// Buyer Protected Endpoints (auth:sanctum)
Route::middleware(['auth:sanctum', 'buyer'])->prefix('buyer')->group(function () {
    // Auth & Profile
    Route::get('/me', [BuyerAuthController::class, 'me']);
    Route::post('/profile', [BuyerAuthController::class, 'updateProfile']);
    Route::post('/change-password', [BuyerAuthController::class, 'changePassword']);
    Route::post('/logout', [BuyerAuthController::class, 'logout']);

    // Cart
    Route::get('/cart', [BuyerCartApiController::class, 'index']);
    Route::post('/cart', [BuyerCartApiController::class, 'store']);
    Route::patch('/cart/{id}', [BuyerCartApiController::class, 'update']);
    Route::delete('/cart/{id}', [BuyerCartApiController::class, 'destroy']);

    // Wishlist
    Route::get('/wishlist', [BuyerCartApiController::class, 'wishlist']);
    Route::post('/wishlist/{productId}/toggle', [BuyerCartApiController::class, 'toggleWishlist']);

    // Vouchers
    Route::get('/vouchers', [BuyerVoucherApiController::class, 'index']);
    Route::post('/vouchers/{id}/claim', [BuyerVoucherApiController::class, 'claim']);
    Route::post('/vouchers/validate', [BuyerVoucherApiController::class, 'validateCode']);

    // Checkout & Orders
    Route::post('/checkout/preview', [BuyerOrderApiController::class, 'checkoutPreview']);
    Route::post('/orders', [BuyerOrderApiController::class, 'store']);
    Route::get('/orders', [BuyerOrderApiController::class, 'index']);
    Route::get('/orders/{id}', [BuyerOrderApiController::class, 'show']);
    Route::get('/orders/{order}/parcels/{parcel}/delivery-otp', [\App\Http\Controllers\BuyerDeliveryOtpController::class, 'show'])
        ->whereNumber('order')->whereNumber('parcel')->middleware('throttle:30,1');
    Route::post('/orders/{order}/parcels/{parcel}/delivery-otp/renew', [\App\Http\Controllers\BuyerDeliveryOtpController::class, 'renew'])
        ->whereNumber('order')->whereNumber('parcel')->middleware('throttle:3,15');
    Route::post('/orders/{id}/cancel', [BuyerOrderApiController::class, 'cancel']);
    Route::post('/orders/{id}/receive', [BuyerOrderApiController::class, 'receive']);
    Route::post('/orders/{id}/reorder', [BuyerOrderApiController::class, 'reorder']);

    // Addresses
    Route::get('/addresses', [BuyerAccountApiController::class, 'addresses']);
    Route::post('/addresses', [BuyerAccountApiController::class, 'storeAddress']);
    Route::patch('/addresses/{id}', [BuyerAccountApiController::class, 'updateAddress']);
    Route::patch('/addresses/{id}/default', [BuyerAccountApiController::class, 'setDefaultAddress']);
    Route::delete('/addresses/{id}', [BuyerAccountApiController::class, 'deleteAddress']);

    // Notifications
    Route::get('/conversations', [\App\Http\Controllers\BuyerConversationController::class, 'mobileIndex']);
    Route::post('/conversations/start/{product}', [\App\Http\Controllers\BuyerConversationController::class, 'start'])->whereNumber('product')->middleware('throttle:30,1');
    Route::post('/conversations/start-seller/{seller}', [\App\Http\Controllers\BuyerConversationController::class, 'startWithSeller'])->whereNumber('seller')->middleware('throttle:30,1');
    Route::get('/conversations/{conversation}', [\App\Http\Controllers\BuyerConversationController::class, 'show'])->whereNumber('conversation');
    Route::post('/conversations/{conversation}', [\App\Http\Controllers\BuyerConversationController::class, 'store'])->whereNumber('conversation')->middleware('throttle:30,1');

    Route::get('/notifications', [BuyerAccountApiController::class, 'notifications']);
    Route::patch('/notifications/{id}/read', [BuyerAccountApiController::class, 'readNotification']);
    Route::post('/notifications/read-all', [BuyerAccountApiController::class, 'readAllNotifications']);

    // Reviews
    Route::get('/reviews', [BuyerAccountApiController::class, 'reviews']);
    Route::post('/products/{id}/reviews', [BuyerAccountApiController::class, 'storeReview']);

    // Store Follow
    Route::post('/store/{sellerId}/toggle-follow', [BuyerAccountApiController::class, 'toggleFollowStore']);
});
