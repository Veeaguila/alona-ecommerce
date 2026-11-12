<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerProductController extends Controller
{
    /**
     * Display the buyer product listing.
     */
    public function index(Request $request): Response
    {
        \App\Models\Order::autoCancelExpiredUnpaidOrders();

        $search = trim((string) $request->input('search', ''));
        $category = $request->input('category', 'all');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'featured');
        $categoryIds = $category !== 'all'
            ? (Category::where('slug', $category)->first()?->getDescendantIds() ?? [])
            : [];

        $products = Product::query()
            ->with([
                'category:id,name,slug',
                'variants',
            ])
            ->where('status', 'approved')

            /*
             |--------------------------------------------------------------------------
             | Search
             |--------------------------------------------------------------------------
             */
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                            $sellerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('store_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery
                                ->where('name', 'like', "%{$search}%");
                        });
                })
            )

            /*
             |--------------------------------------------------------------------------
             | Category
             |--------------------------------------------------------------------------
             */
            ->when(
                $category !== 'all',
                fn ($query) => $query->whereIn('category_id', $categoryIds)
            )

            /*
             |--------------------------------------------------------------------------
             | Maximum Price
             |--------------------------------------------------------------------------
             */
            ->when(
                is_numeric($maxPrice),
                fn ($query) => $query->where(
                    'price',
                    '<=',
                    (float) $maxPrice
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */
        switch ($sort) {
            case 'price-low':
                $products->orderBy('price', 'asc');
                break;

            case 'price-high':
                $products->orderBy('price', 'desc');
                break;

            case 'rating':
                $products
                    ->orderByDesc('rating')
                    ->orderByDesc('reviews_count');
                break;

            case 'latest':
                $products->latest();
                break;

            case 'featured':
            default:
                // Seeded random order to ensure cross-store product diversity with stable pagination
                $seed = (int) $request->session()->remember('catalog_seed', function () use ($request) {
                    return abs(crc32($request->session()->getId() . date('YmdH')));
                });
                if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
                    $products->inRandomOrder($seed);
                } else {
                    $products->orderByRaw("RAND({$seed})");
                }
                break;
        }

        $products = $products
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Buyer/Products', [
            'products' => $products,

            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'name',
                    'slug',
                ]),

            'filters' => [
                'search' => $search,
                'category' => $category,
                'max_price' => is_numeric($maxPrice) ? (float) $maxPrice : null,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Display a single approved product.
     */
    public function show(Product $product): Response
    {
        \App\Models\Order::autoCancelExpiredUnpaidOrders();
        $product->refresh();

        /*
        |--------------------------------------------------------------------------
        | Only approved products can be viewed.
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $product->status === 'approved',
            404
        );

        /*
        |--------------------------------------------------------------------------
        | Recently Viewed Products
        |--------------------------------------------------------------------------
        |
        | Only authenticated buyers are recorded.
        |
        | IMPORTANT:
        | The application uses "usertype" in the users table,
        | not "role".
        |
        | updateOrCreate() ensures:
        |
        | - First view  = create record
        | - Repeat view = update viewed_at
        | - No duplicate user/product records
        |
        |--------------------------------------------------------------------------
        */
        if (
            auth()->check() &&
            auth()->user()->usertype === 'buyer'
        ) {
            RecentlyViewedProduct::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'product_id' => $product->id,
                ],
                [
                    'viewed_at' => now(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Load Product Relationships
        |--------------------------------------------------------------------------
        */
        $product->load([
            'category:id,name,slug',

            'seller:id,name,store_name,store_description,store_logo_path,return_policy_days',

            'variants',

            'images:id,product_id,image_path',

            'reviews' => fn ($query) => $query
                ->with('user:id,name')
                ->latest(),

            'questions' => fn ($query) => $query
                ->where('is_public', true)
                ->whereNotNull('answer')
                ->with([
                    'buyer:id,name',
                    'answeredBy:id,name',
                ])
                ->latest()
                ->limit(5),
        ]);

        $relatedProducts = Product::query()
            ->with('category:id,name')
            ->where('status', 'approved')
            ->where('id', '!=', $product->id)
            ->when(
                $product->category_id,
                fn ($query) => $query->where('category_id', $product->category_id)
            )
            ->latest()
            ->take(5)
            ->get([
                'id',
                'category_id',
                'name',
                'price',
                'image_path',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Seller Statistics
        |--------------------------------------------------------------------------
        */
        $sellerStats = [
            'total_products' => Product::where(
                'seller_id',
                $product->seller_id
            )
                ->where('status', 'approved')
                ->count(),

            'avg_rating' => Product::where(
                'seller_id',
                $product->seller_id
            )
                ->where('status', 'approved')
                ->whereNotNull('rating')
                ->avg('rating'),

            'followers_count' => \App\Models\StoreFollower::where('seller_id', $product->seller_id)->count(),
        ];

        $vouchers = \App\Models\Voucher::query()
            ->where('seller_id', $product->seller_id)
            ->where('is_active', true)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'ended_at'), function ($q) {
                $q->whereNull('ended_at');
            })
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'display_mode'), function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('display_mode')
                        ->orWhere('display_mode', '!=', 'code_only');
                });
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhereDate('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->latest()
            ->get()
            ->filter(fn (\App\Models\Voucher $v) => $v->appliesToProduct((int) $product->id, (int) $product->seller_id))
            ->values();

        $claimedVoucherIds = [];
        if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('user_vouchers')) {
            $claimedVoucherIds = \App\Models\UserVoucher::where('user_id', auth()->id())
                ->whereIn('voucher_id', $vouchers->pluck('id'))
                ->where('status', 'claimed')
                ->pluck('voucher_id')
                ->toArray();
        }

        // Active Bundle Deals for this Seller & Product
        $bundleDeals = collect();
        if ($product->seller_id && \Illuminate\Support\Facades\Schema::hasTable('seller_promotions')) {
            $now = now();
            $bundleDeals = \App\Models\SellerPromotion::query()
                ->where('seller_id', $product->seller_id)
                ->where('type', 'bundle')
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                })
                ->get()
                ->filter(fn (\App\Models\SellerPromotion $promo) => $promo->appliesToBundleProduct((int) $product->id))
                ->map(function ($bundle) use ($product) {
                    $productIds = (array) ($bundle->product_ids ?? []);
                    if ($bundle->product_id) {
                        $productIds[] = $bundle->product_id;
                    }
                    $sampleProducts = Product::whereIn('id', array_slice($productIds, 0, 4))
                        ->where('status', 'approved')
                        ->get(['id', 'name', 'price', 'image_path']);

                    return [
                        'id' => $bundle->id,
                        'name' => $bundle->name,
                        'seller_id' => $bundle->seller_id,
                        'min_quantity' => $bundle->min_quantity ?? 2,
                        'discount_value' => (float) $bundle->discount_value,
                        'discount_type' => $bundle->discount_type,
                        'product_ids' => $productIds,
                        'sample_products' => $sampleProducts->map(fn ($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'price' => (float) $p->price,
                            'image' => $p->image_path
                                ? (str_starts_with($p->image_path, 'http') ? $p->image_path : '/storage/' . ltrim($p->image_path, '/'))
                                : null,
                        ]),
                    ];
                })
                ->values();
        }

        $userPurchasedCount = 0;
        $userRemainingLimit = null;
        $inWishlist = false;
        $isFollowingStore = false;

        if (auth()->check()) {
            $userPurchasedCount = $product->purchasedUnitsCountFor(auth()->user());
            $userRemainingLimit = $product->remainingPurchaseLimitFor(auth()->user());
            $inWishlist = \App\Models\Wishlist::where('user_id', auth()->id())
                ->where('product_id', $product->id)
                ->exists();
            if ($product->seller_id) {
                $isFollowingStore = \App\Models\StoreFollower::where('user_id', auth()->id())
                    ->where('seller_id', $product->seller_id)
                    ->exists();
            }
        }

        return Inertia::render('Buyer/Show', [
            'product' => $product,
            'vouchers' => $vouchers,
            'claimed_voucher_ids' => $claimedVoucherIds,
            'bundle_deals' => $bundleDeals,
            'relatedProducts' => $relatedProducts,
            'sellerStats' => $sellerStats,
            'isGuest' => !auth()->check(),
            'inWishlist' => $inWishlist,
            'isFollowingStore' => $isFollowingStore,
            'userPurchasedCount' => $userPurchasedCount,
            'userRemainingLimit' => $userRemainingLimit,
        ]);
    }
}
