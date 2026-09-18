<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\GuestProductController;
use App\Http\Controllers\BuyerProductController;
use App\Http\Controllers\BuyerCartController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\BuyerAddressController;
use App\Http\Controllers\BuyerReviewController;
use App\Http\Controllers\BuyerNotificationController;
use App\Http\Controllers\BuyerMessageController;
use App\Http\Controllers\BuyerConversationController;
use App\Http\Controllers\BuyerCategoryController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerVoucherController;
use App\Http\Controllers\SellerReviewController;
use App\Http\Controllers\SellerNotificationController;
use App\Http\Controllers\SellerConversationController;
use App\Http\Controllers\SellerCustomerController;
use App\Http\Controllers\SellerReportController;
use App\Http\Controllers\SellerStoreController;
use App\Http\Controllers\SellerSettingController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminSellerApplicationController;
use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\AdminComplianceController;
use App\Http\Controllers\AdminComplaintController;
use App\Http\Controllers\AdminReportingController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\BuyerComplaintController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/*
|--------------------------------------------------------------------------
| PUBLIC STOREFRONT
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Guest/Home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,

        'categories' => Category::where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
            ]),

        'products' => Product::with('category:id,name')
            ->where('status', 'approved')
            ->latest()
            ->take(6)
            ->get(),
    ]);
});


/*
|--------------------------------------------------------------------------
| GUEST PRODUCTS
|--------------------------------------------------------------------------
*/

Route::get('/products', [
    GuestProductController::class,
    'index',
])->name('guest.products');

Route::get('/product/{product}', [
    GuestProductController::class,
    'show',
])->name('guest.product');

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
| GOOGLE AUTH
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
| PHILIPPINE ADDRESS API
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
    \App\Http\Controllers\BuyerStoreController::class,
    'show',
])->name('buyer.store.show');

Route::get('/buyer/categories', [
    BuyerCategoryController::class,
    'index',
])->name('buyer.categories');


/*
|--------------------------------------------------------------------------
| BUYER DASHBOARD
|--------------------------------------------------------------------------
*/

Route::get('/buyer', function () {
    $user = auth()->user();

    /*
    |--------------------------------------------------------------------------
    | Recently Viewed Products
    |--------------------------------------------------------------------------
    |
    | Get the latest 6 approved products viewed by this buyer.
    |
    |--------------------------------------------------------------------------
    */

    $recentlyViewedProducts = \App\Models\RecentlyViewedProduct::query()
        ->where('user_id', $user->id)

        ->whereHas('product', function ($query) {
            $query->where('status', 'approved');
        })

        ->with([
            'product' => function ($query) {
                $query
                    ->where('status', 'approved')

                    ->with([
                        'category:id,name,slug',

                        'images:id,product_id,image_path,sort_order',
                    ]);
            },
        ])

        ->orderByDesc('viewed_at')

        ->take(6)

        ->get()

        ->map(function ($recentlyViewed) {
            $product = $recentlyViewed->product;

            /*
            |--------------------------------------------------------------------------
            | Skip deleted / unavailable products
            |--------------------------------------------------------------------------
            */
            if (!$product) {
                return null;
            }

            return [
                'id' => $product->id,

                'name' => $product->name,

                'slug' => $product->slug,

                'price' => $product->price,

                'old_price' => $product->old_price,

                'rating' => $product->rating,

                'reviews_count' => $product->reviews_count,

                'image_path' => $product->image_path,

                /*
                |--------------------------------------------------------------------------
                | Category
                |--------------------------------------------------------------------------
                */
                'category' => $product->category
                    ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'slug' => $product->category->slug,
                    ]
                    : null,

                /*
                |--------------------------------------------------------------------------
                | Additional Product Images
                |--------------------------------------------------------------------------
                */
                'images' => $product->images
                    ->map(fn ($image) => [
                        'id' => $image->id,

                        'image_path' => $image->image_path,

                        'sort_order' => $image->sort_order,
                    ])
                    ->values()
                    ->all(),

                /*
                |--------------------------------------------------------------------------
                | When the product was viewed
                |--------------------------------------------------------------------------
                */
                'viewed_at' => $recentlyViewed->viewed_at
                    ?->toISOString(),
            ];
        })

        ->filter()

        ->values()

        ->all();

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    return Inertia::render('Buyer/Dashboard', [
        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */
        'categories' => \App\Models\Category::query()
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

        /*
        |--------------------------------------------------------------------------
        | Latest Products
        |--------------------------------------------------------------------------
        */
        'products' => \App\Models\Product::query()
            ->with('category:id,name')

            ->where('status', 'approved')

            ->latest()

            ->take(4)

            ->get(),

        /*
        |--------------------------------------------------------------------------
        | Recently Viewed
        |--------------------------------------------------------------------------
        */
        'recentlyViewedProducts' => $recentlyViewedProducts,
    ]);
})
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
| BUYER ORDERS
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

    Route::patch('/buyer/order-items/{orderItem}/confirm-delivery', [
        BuyerOrderController::class,
        'confirmDelivery',
    ])->name('buyer.order-items.confirm-delivery');

});


/*
|--------------------------------------------------------------------------
| BUYER REVIEWS / NOTIFICATIONS / MESSAGES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'buyer',
])->group(function () {

    Route::post('/buyer/complaints', [
        BuyerComplaintController::class,
        'store',
    ])->name('buyer.complaints.store');

    Route::get('/buyer/reviews', [
        BuyerReviewController::class,
        'index',
    ])->name('buyer.reviews');

    Route::post('/buyer/products/{product}/reviews', [
        BuyerReviewController::class,
        'store',
    ])->name('buyer.reviews.store');

    Route::get('/buyer/products/{product}/reviews', [
    BuyerReviewController::class,
    'productReviews',
    ])->name('buyer.product.reviews');

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
        \App\Http\Controllers\BuyerOrderConversationController::class,
        'start',
    ])->name('buyer.order-items.contact-seller');

    // Product Q&A

    Route::post('/buyer/products/{product}/questions', [
        \App\Http\Controllers\BuyerProductQuestionController::class,
        'store',
    ])->name('buyer.products.questions.store');

});

/*
|--------------------------------------------------------------------------
| BUYER ACCOUNT
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

});

/*
|--------------------------------------------------------------------------
| BUYER ADDRESSES
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

});



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
        \App\Http\Controllers\SellerProductQuestionController::class,
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
    | SELLER ORDER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::get('/seller/orders', [
        SellerOrderController::class,
        'index',
    ])->name('seller.orders');

    Route::get('/seller/orders/{orderItem}', [
        SellerOrderController::class,
        'show',
    ])->name('seller.order.details');


    /*
    |--------------------------------------------------------------------------
    | ORDER FULFILLMENT
    |--------------------------------------------------------------------------
    |
    | Seller workflow:
    |
    | Pending → Processing → Packed → Ready for Pickup
    |
    | After Ready for Pickup:
    |
    | Logistics / Courier handles the shipment.
    |
    */

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


/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth',
        'admin',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | ADMIN DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/', [
            AdminDashboardController::class,
            'index',
        ])->name('admin.dashboard');


        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        Route::get('/users', [
            AdminUserController::class,
            'index',
        ])->name('admin.users');

        Route::get('/users/{user}', [
            AdminUserController::class,
            'show',
        ])->name('admin.users.show');

        Route::patch('/users/{user}/status', [
            AdminUserController::class,
            'updateStatus',
        ])->name('admin.users.status');


        /*
        |--------------------------------------------------------------------------
        | APPLICATIONS
        |--------------------------------------------------------------------------
        */

        Route::get('/applications', [
            AdminApplicationController::class,
            'index',
        ])->name('admin.applications');

        Route::get('/applications/{user}', [
            AdminApplicationController::class,
            'show',
        ])->name('admin.applications.show');

        Route::patch('/applications/{user}/approve', [
            AdminApplicationController::class,
            'approve',
        ])->name('admin.applications.approve');

        Route::patch('/applications/{user}/reject', [
            AdminApplicationController::class,
            'reject',
        ])->name('admin.applications.reject');


        /*
        |--------------------------------------------------------------------------
        | SELLER APPLICATIONS
        |--------------------------------------------------------------------------
        */

        Route::get('/sellers/applications', [
            AdminSellerApplicationController::class,
            'index',
        ])->name('admin.seller-applications');

        Route::patch('/sellers/applications/{user}/approve', [
            AdminSellerApplicationController::class,
            'approve',
        ])->name('admin.seller-applications.approve');

        Route::patch('/sellers/applications/{user}/reject', [
            AdminSellerApplicationController::class,
            'reject',
        ])->name('admin.seller-applications.reject');


        /*
        |--------------------------------------------------------------------------
        | SELLER COMPLIANCE
        |--------------------------------------------------------------------------
        */

        Route::get('/compliance', [
            AdminComplianceController::class,
            'index',
        ])->name('admin.compliance');

        Route::patch('/compliance/{product}/status', [
            AdminComplianceController::class,
            'updateStatus',
        ])->name('admin.compliance.status');

        Route::patch('/compliance/{product}/unsuspend', [
            AdminComplianceController::class,
            'unsuspend',
        ])->name('admin.compliance.unsuspend');


        /*
        |--------------------------------------------------------------------------
        | COMPLAINTS
        |--------------------------------------------------------------------------
        */

        Route::get('/complaints', [
            AdminComplaintController::class,
            'index',
        ])->name('admin.complaints');

        Route::get('/complaints/{complaint}', [
            AdminComplaintController::class,
            'show',
        ])->name('admin.complaints.show');

        Route::patch('/complaints/{complaint}/resolve', [
            AdminComplaintController::class,
            'resolve',
        ])->name('admin.complaints.resolve');


        /*
        |--------------------------------------------------------------------------
        | REPORTS
        |--------------------------------------------------------------------------
        */

        Route::get('/reports/sales-summary', [
            AdminReportingController::class,
            'salesSummary',
        ])->name('admin.reports.sales-summary');

        Route::get('/reports/commission', [
            AdminReportingController::class,
            'commissionReport',
        ])->name('admin.reports.commission');


        /*
        |--------------------------------------------------------------------------
        | ADMIN SETTINGS
        |--------------------------------------------------------------------------
        */

        Route::get('/settings', [
            AdminSettingsController::class,
            'settings',
        ])->name('admin.settings');

        Route::get('/settings/announcements', [
            AdminSettingsController::class,
            'announcements',
        ])->name('admin.announcements');

        Route::post('/settings/announcements', [
            AdminSettingsController::class,
            'storeAnnouncement',
        ])->name('admin.announcements.store');

        Route::get('/settings/policies', [
            AdminSettingsController::class,
            'policies',
        ])->name('admin.policies');

        Route::post('/settings/policies', [
            AdminSettingsController::class,
            'storePolicy',
        ])->name('admin.policies.store');

        Route::patch('/settings/policies/{policy}', [
            AdminSettingsController::class,
            'updatePolicy',
        ])->name('admin.policies.update');

        Route::post('/settings/platform', [
            AdminSettingsController::class,
            'storePlatformSetting',
        ])->name('admin.platform-settings.store');


        /*
        |--------------------------------------------------------------------------
        | MESSAGES
        |--------------------------------------------------------------------------
        */

        Route::get('/messages', [
            AdminMessageController::class,
            'index',
        ])->name('admin.messages');

        Route::get('/messages/{conversation}', [
            AdminMessageController::class,
            'show',
        ])->name('admin.messages.show');

        Route::post('/messages/{conversation}', [
            AdminMessageController::class,
            'store',
        ])->name('admin.messages.store');

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
| PROFILE
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/profile', [
        ProfileController::class,
        'edit',
    ])->name('profile.edit');

    Route::patch('/profile', [
        ProfileController::class,
        'update',
    ])->name('profile.update');

    Route::delete('/profile', [
        ProfileController::class,
        'destroy',
    ])->name('profile.destroy');

});


/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
