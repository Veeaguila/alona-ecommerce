<?php

use App\Http\Controllers\DeliveryCoverageController;
use App\Http\Controllers\PartnerProfileController;
use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\PhilippineAddressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

Route::redirect('/sorting-center/login', '/?auth=login')->name('sorting-center.login');
Route::redirect('/sorting-center/register', '/register/logistics')->name('sorting-center.register');

// These views contain only the UI shell; every data endpoint requires an approved account.
Route::prefix('sorting-center')->middleware(['auth', 'sorting_center.approved'])->group(function () {
    foreach (['dashboard', 'order-management', 'delivery-management', 'rider-assignment', 'delivery-monitoring', 'delivery-reports', 'messages', 'pickup-requests'] as $page) {
        Route::get($page, function (Request $request) use ($page) {
            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::location($request->fullUrl());
            }

            $token = $request->session()->get('logistics.web_token');
            $accessToken = $token ? PersonalAccessToken::findToken($token) : null;
            if (! $accessToken || $accessToken->tokenable_id !== $request->user()->id || $accessToken->expires_at?->isPast()) {
                if ($accessToken?->tokenable_id === $request->user()->id) {
                    $accessToken->delete();
                }
                $token = $request->user()->createToken('logistics-web', ['*'], now()->addHours(2))->plainTextToken;
                $request->session()->put('logistics.web_token', $token);
            }

            return view('sorting-center.'.$page, ['logisticsSession' => [
                'token' => $token, 'user' => $request->user()->only(['id', 'name', 'email']),
                'sorting_center' => $request->user()->sortingCenter->only(['id', 'business_name']),
            ]]);
        })->name('sorting-center.'.$page);
    }
    Route::get('/riders', [\App\Http\Controllers\Logistics\RiderManagementController::class, 'index'])->name('sorting-center.riders');
    Route::post('/riders', [\App\Http\Controllers\Logistics\RiderManagementController::class, 'store'])->name('sorting-center.riders.store');
    Route::post('/riders/{id}/resend-activation', [\App\Http\Controllers\Logistics\RiderManagementController::class, 'resendActivation'])->name('sorting-center.riders.resend');
});

Route::get('/rider/activate/{token}', [\App\Http\Controllers\RiderActivationController::class, 'show'])->name('rider.activate');
Route::post('/rider/activate/{token}', [\App\Http\Controllers\RiderActivationController::class, 'activate'])->name('rider.activate.submit');

Route::get('/api/addresses/{level}', [PhilippineAddressController::class, 'index'])->middleware('throttle:120,1');
Route::get('/api/delivery-coverage', [DeliveryCoverageController::class, 'index'])->middleware('throttle:120,1');
Route::middleware('guest')->group(function () {
    Route::get('/register/logistics', [\App\Http\Controllers\LogisticsRegistrationController::class, 'create'])->name('register.logistics');
    Route::post('/register/logistics', [\App\Http\Controllers\LogisticsRegistrationController::class, 'store'])->middleware('throttle:6,1');
    foreach (['rider'] as $role) {
        Route::get('/register/'.$role, [PartnerRegistrationController::class, 'create'])->name('register.'.$role);
        Route::post('/register/'.$role, [PartnerRegistrationController::class, 'store'])->middleware('throttle:6,1');
    }
});
Route::middleware('auth')->group(function () {
    Route::post('/logistics/verify-email', [\App\Http\Controllers\LogisticsRegistrationController::class, 'verify'])->middleware('throttle:6,1')->name('logistics.otp.verify');
    Route::post('/logistics/resend-code', [\App\Http\Controllers\LogisticsRegistrationController::class, 'resend'])->middleware('throttle:3,1')->name('logistics.otp.resend');
    Route::post('/logistics/resubmit', [\App\Http\Controllers\LogisticsRegistrationController::class, 'resubmit'])->middleware('throttle:6,1')->name('logistics.resubmit');
    Route::get('/application/status', [PartnerProfileController::class, 'status'])->name('application.status');
    Route::patch('/partner/profile', [PartnerProfileController::class, 'update'])->name('partner.profile.update');
    Route::get('/partner/documents/{user}/{document}', [PartnerProfileController::class, 'document'])->name('partner.documents');
    Route::get('/rider/dashboard', [PartnerProfileController::class, 'rider'])->middleware('rider.approved')->name('rider.dashboard');
});
