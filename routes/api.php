<?php

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
Route::post('/sorting-center/login', [LoginSortingCenterController::class, 'login'])->middleware('throttle:6,1');

// Public Rider Registration (for seeding & future Flutter Rider App)
Route::post('/rider/register', [PartnerRegistrationController::class, 'store'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/partner/profile', [PartnerProfileController::class, 'status']);
    Route::patch('/partner/profile', [PartnerProfileController::class, 'update']);
    Route::patch('/rider/profile', [\App\Http\Controllers\Logistics\RiderProfileController::class, 'update']);
    Route::get('/rider/profile-photo', [\App\Http\Controllers\Logistics\RiderProfileController::class, 'photo']);
    Route::post('/rider/profile-photo', [\App\Http\Controllers\Logistics\RiderProfileController::class, 'uploadPhoto'])->middleware('throttle:20,1');
    // Own notifications remain readable while approval is pending/rejected.
    Route::get('/rider/notifications', [\App\Http\Controllers\Logistics\RiderNotificationController::class, 'index']);
    Route::post('/rider/notifications/{id}/read', [\App\Http\Controllers\Logistics\RiderNotificationController::class, 'read']);
    Route::get('/user', fn (Request $request) => $request->user()->only(['id', 'name', 'email', 'usertype', 'status']));
});
Route::post('/rider/login', [LoginRiderController::class, 'login'])->middleware('throttle:6,1');
Route::get('/rider/activate/{token}', [\App\Http\Controllers\RiderActivationController::class, 'show']);
Route::post('/rider/activate/{token}', [\App\Http\Controllers\RiderActivationController::class, 'activate']);

Route::middleware(['auth:sanctum', 'rider.approved'])->prefix('rider')->group(function () {
    Route::get('/earnings', [\App\Http\Controllers\DeliveryFinanceController::class, 'rider']);
    Route::get('/pickups/available', [RiderPickupController::class, 'available']);
    Route::get('/pickups/mine', [RiderPickupController::class, 'mine']);
    Route::post('/pickups/{parcelId}/accept', [RiderPickupController::class, 'accept']);
    Route::post('/pickups/{parcelId}/confirm', [RiderPickupController::class, 'confirm']);
    Route::get('/deliveries', [RiderDeliveryController::class, 'index']);
    Route::get('/deliveries/lookup', [RiderDeliveryController::class, 'lookup'])->middleware('throttle:60,1');
    Route::post('/deliveries/{parcelId}/start', [RiderDeliveryController::class, 'start']);
    Route::post('/deliveries/{parcelId}/deliver', [RiderDeliveryController::class, 'deliver']);
    Route::post('/deliveries/{parcelId}/upload-proof', [RiderDeliveryController::class, 'uploadProof']);
    Route::post('/deliveries/{parcelId}/fail', [RiderDeliveryController::class, 'fail']);
    Route::get('/messages/recipients', [MessageController::class, 'recipients']);
    Route::get('/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/messages/conversations/{conversationId}', [MessageController::class, 'show']);
    Route::post('/messages', [MessageController::class, 'send']);
    Route::post('/messages/conversations/{conversationId}', [MessageController::class, 'send']);
});

// Protected Sorting Center Management
Route::middleware(['auth:sanctum', 'sorting_center.approved'])->group(function () {
    Route::post('/sorting-center/coverage', [DeliveryCoverageController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/sorting-center/messages/recipients', [MessageController::class, 'recipients']);
    Route::get('/sorting-center/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/sorting-center/messages/conversations/{conversationId}', [MessageController::class, 'show']);
    Route::post('/sorting-center/messages', [MessageController::class, 'send']);
    Route::post('/sorting-center/messages/conversations/{conversationId}', [MessageController::class, 'send']);
    Route::get('/sorting-center/riders', [RiderManagementController::class, 'index']);
    Route::post('/sorting-center/riders', [RiderManagementController::class, 'store']);
    Route::post('/sorting-center/riders/{id}/resend-activation', [RiderManagementController::class, 'resendActivation']);
    Route::post('/sorting-center/riders/{id}/approve', [RiderManagementController::class, 'approve']);
    Route::post('/sorting-center/riders/{id}/reject', [RiderManagementController::class, 'reject']);
    Route::post('/sorting-center/riders/{id}/activate', [RiderManagementController::class, 'activate']);
    Route::post('/sorting-center/riders/{id}/deactivate', [RiderManagementController::class, 'deactivate']);
    Route::get('/sorting-center/pickup-requests', [PickupRequestController::class, 'index']);
    Route::post('/sorting-center/pickup-requests/{id}/confirm', [PickupRequestController::class, 'confirm']);
    Route::post('/sorting-center/parcels/mark-arrived', [IncomingParcelController::class, 'markArrived']);
    Route::get('/sorting-center/parcels/lookup/{orderId}', [IncomingParcelController::class, 'lookup']);
    Route::get('/sorting-center/parcels', [ParcelSortingController::class, 'index']);
    Route::get('/sorting-center/areas', [ParcelSortingController::class, 'areas']);
    Route::post('/sorting-center/areas', [ParcelSortingController::class, 'createArea']);
    Route::post('/sorting-center/parcels/{id}/sort', [ParcelSortingController::class, 'sort']);
    Route::get('/sorting-center/parcels/{id}/eligible-riders', [ParcelAssignmentController::class, 'eligibleRiders']);
    Route::post('/sorting-center/parcels/{id}/assign', [ParcelAssignmentController::class, 'assign']);
    Route::get('/sorting-center/parcels/monitoring', [ParcelMonitoringController::class, 'index']);
    Route::get('/sorting-center/dashboard', [DashboardController::class, 'index']);
    Route::get('/sorting-center/reports', [ReportController::class, 'index']);
    Route::get('/sorting-center/reports/delivery-stats', [ReportController::class, 'deliveryStats']);
    Route::get('/sorting-center/reports/transactions', [ReportController::class, 'transactions']);
    Route::get('/sorting-center/parcels/{parcelId}/proofs', [DeliveryProofController::class, 'getProofs']);
    Route::get('/sorting-center/proofs/{proofId}', [DeliveryProofController::class, 'getProof']);
    Route::get('/sorting-center/proofs/{proofId}/file', [DeliveryProofController::class, 'file'])
        ->name('sorting-center.proofs.file');
});

// Seller API Routes
Route::middleware(['auth:sanctum', 'seller'])->prefix('seller')->group(function () {
    Route::get('/delivery-charges', [\App\Http\Controllers\DeliveryFinanceController::class, 'seller']);
    Route::post('/pickup-requests', [SellerPickupRequestController::class, 'store']);
});

Route::post('/logistics/logout', function (Request $request) {
    $request->user()->currentAccessToken()?->delete();

    return response()->json(['message' => 'Signed out.']);
})->middleware('auth:sanctum');

// Payment Webhook (BUY-PAY-005)
Route::post('/webhooks/payment', [\App\Http\Controllers\BuyerOrderController::class, 'paymentWebhook'])->name('payment.webhook');
