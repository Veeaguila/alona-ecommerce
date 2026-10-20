<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BuyerDashboardController;
use App\Http\Controllers\BuyerStoreController;
use App\Http\Controllers\BuyerSupportController;
use App\Http\Controllers\GuestProductController;
use App\Http\Controllers\ProfileController;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| PUBLIC STOREFRONT & GUEST ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', [BuyerDashboardController::class, 'guest']);

Route::get('/products', [
    GuestProductController::class,
    'index',
])->name('guest.products');

Route::get('/product/{product}', [
    GuestProductController::class,
    'show',
])->name('guest.product');

Route::get('/store/{seller}', [
    BuyerStoreController::class,
    'show',
])->name('guest.store');

Route::get('/help', [
    BuyerSupportController::class,
    'help',
])->name('guest.help');

Route::get('/faq', [
    BuyerSupportController::class,
    'faq',
])->name('guest.faq');

Route::get('/policies', [
    BuyerSupportController::class,
    'policies',
])->name('guest.policies');

Route::get('/categories', function () {
    return Inertia::render('Guest/Categories', [
        'categories' => Category::query()
            ->where('is_active', true)
            ->withCount([
                'products' => fn ($query) =>
                    $query->where('status', 'approved'),
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
            ]),
    ]);
})->name('guest.categories');


/*
|--------------------------------------------------------------------------
| GOOGLE AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::get('/auth/google', [
    GoogleController::class,
    'redirect',
])->name('google.redirect');

Route::get('/auth/google/callback', [
    GoogleController::class,
    'callback',
])->name('google.callback');


/*
|--------------------------------------------------------------------------
| PHILIPPINE ADDRESS API (PSGC Cloud Proxy)
|--------------------------------------------------------------------------
|
| Proxies PSGC Cloud requests through Laravel so the frontend can use:
|   /api/philippines/provinces
|   /api/philippines/provinces/{province}/cities-municipalities
|   /api/philippines/cities-municipalities/{city}/barangays
|--------------------------------------------------------------------------
*/

Route::get('/api/philippines/provinces', function () {
    try {
        $response = Http::withoutVerifying()
            ->timeout(20)
            ->acceptJson()
            ->get('https://psgc.cloud/api/provinces');

        if ($response->failed()) {
            return response()->json([
                'message' => 'PSGC Cloud province request failed.',
                'status' => $response->status(),
                'body' => $response->body(),
            ], 502);
        }

        $data = $response->json();

        if (is_array($data)) {
            return response()->json($data);
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return response()->json($data['data']);
        }

        return response()->json([]);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Unable to connect to PSGC Cloud.',
            'error' => $e->getMessage(),
        ], 502);
    }
});

Route::get('/api/philippines/provinces/{province}/cities-municipalities', function ($province) {
    try {
        $response = Http::withoutVerifying()
            ->timeout(20)
            ->acceptJson()
            ->get(
                'https://psgc.cloud/api/provinces/' .
                urlencode($province) .
                '/cities-municipalities'
            );

        if ($response->failed()) {
            return response()->json([
                'message' => 'PSGC Cloud city/municipality request failed.',
                'status' => $response->status(),
                'body' => $response->body(),
            ], 502);
        }

        $data = $response->json();

        if (is_array($data)) {
            return response()->json($data);
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return response()->json($data['data']);
        }

        return response()->json([]);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Unable to connect to PSGC Cloud.',
            'error' => $e->getMessage(),
        ], 502);
    }
});

Route::get('/api/philippines/cities-municipalities/{city}/barangays', function ($city) {
    try {
        $response = Http::withoutVerifying()
            ->timeout(20)
            ->acceptJson()
            ->get(
                'https://psgc.cloud/api/cities-municipalities/' .
                urlencode($city) .
                '/barangays'
            );

        if ($response->failed()) {
            return response()->json([
                'message' => 'PSGC Cloud barangay request failed.',
                'status' => $response->status(),
                'body' => $response->body(),
            ], 502);
        }

        $data = $response->json();

        if (is_array($data)) {
            return response()->json($data);
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return response()->json($data['data']);
        }

        return response()->json([]);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Unable to connect to PSGC Cloud.',
            'error' => $e->getMessage(),
        ], 502);
    }
});


/*
|--------------------------------------------------------------------------
| DEFAULT DASHBOARD
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware([
    'auth',
    'verified',
])->name('dashboard');


/*
|--------------------------------------------------------------------------
| PROFILE MANAGEMENT
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/profile', [
        ProfileController::class,
        'edit',
    ])->name('profile.edit');

    Route::match(['patch', 'post'], '/profile', [
        ProfileController::class,
        'update',
    ])->name('profile.update');

    Route::post('/profile/email/verify-otp', [
        ProfileController::class,
        'verifyEmailChange',
    ])->name('profile.email.verify');

    Route::post('/profile/email/resend-otp', [
        ProfileController::class,
        'resendEmailChangeOtp',
    ])->name('profile.email.resend');

    Route::post('/profile/email/cancel', [
        ProfileController::class,
        'cancelEmailChange',
    ])->name('profile.email.cancel');

    Route::post('/profile/phone/verify-otp', [
        ProfileController::class,
        'verifyPhoneChange',
    ])->name('profile.phone.verify');

    Route::post('/profile/phone/resend-otp', [
        ProfileController::class,
        'resendPhoneChangeOtp',
    ])->name('profile.phone.resend');

    Route::post('/profile/phone/cancel', [
        ProfileController::class,
        'cancelPhoneChange',
    ])->name('profile.phone.cancel');

    Route::delete('/profile', [
        ProfileController::class,
        'destroy',
    ])->name('profile.destroy');

});


/*
|--------------------------------------------------------------------------
| MODULAR ROUTE INCLUDES
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
require __DIR__ . '/buyer.php';
require __DIR__ . '/seller.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/logistics.php';

/*
|--------------------------------------------------------------------------
| ARCHITECTURE & SECURITY SPECIFICATION DOWNLOAD ROUTES
|--------------------------------------------------------------------------
*/
Route::get('/docs/architecture', function () {
    return response()->file(public_path('SYSTEM_ARCHITECTURE_AND_SECURITY.html'));
})->name('docs.architecture');

Route::get('/docs/architecture/download', function () {
    return response()->download(public_path('SYSTEM_ARCHITECTURE_AND_SECURITY.html'), 'Alona_System_Architecture_and_Security.html');
})->name('docs.architecture.download');

Route::get('/docs/architecture/markdown', function () {
    return response()->download(public_path('SYSTEM_ARCHITECTURE_AND_SECURITY.md'), 'Alona_System_Architecture_and_Security.md');
})->name('docs.architecture.markdown');

Route::get('/docs/architecture/docx', function () {
    return response()->download(public_path('Alona_System_Architecture_and_Security.docx'), 'Alona_System_Architecture_and_Security.docx');
})->name('docs.architecture.docx');
