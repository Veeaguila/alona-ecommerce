<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BuyerDashboardController extends Controller
{
    /**
     * Buyer marketplace dashboard.
     */
    public function __invoke(Request $request): Response
    {
        return $this->renderDashboard($request);
    }

    /**
     * Public marketplace homepage.
     */
    public function guest(Request $request): Response
    {
        return $this->renderDashboard($request, true);
    }

    private function renderDashboard(Request $request, bool $guest = false): Response
    {
        $user = $guest ? null : $request->user();

        /*
        |--------------------------------------------------------------------------
        | Shared product query
        |--------------------------------------------------------------------------
        |
        | Only approved products are allowed onto the buyer marketplace.
        |
        */
        $approvedProductQuery = Product::query()
            ->where('status', 'approved')
            ->with([
                'category:id,name,slug',
                'seller:id,name',
                'images',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount([
                'products' => function ($query) {
                    $query->where('status', 'approved');
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'image_path' => $category->image_path,
                    'products_count' => (int) $category->products_count,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Featured Products
        |--------------------------------------------------------------------------
        |
        | Explicitly managed through products.is_featured.
        |
        */
        $featuredProducts = (clone $approvedProductQuery)
            ->where('is_featured', true)
            ->where('stock', '>', 0)
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(fn ($product) => $this->formatProduct($product))
            ->values();

/*
|--------------------------------------------------------------------------
| Top Selling Products
|--------------------------------------------------------------------------
|
| Sales are calculated from actual OrderItem quantities.
| Cancelled items are excluded.
|
*/
$topSellingIds = DB::table('order_items')
    ->select(
        'product_id',
        DB::raw('SUM(quantity) as sold_count')
    )
    ->whereNotNull('product_id')
    ->where('status', '!=', 'cancelled')
    ->groupBy('product_id')
    ->orderByDesc('sold_count')
    ->limit(12)
    ->pluck('sold_count', 'product_id');

$topSellingProducts = collect();

if ($topSellingIds->isNotEmpty()) {
    $topSellingProducts = (clone $approvedProductQuery)
        ->whereIn('id', $topSellingIds->keys())
        ->get()
        ->sortByDesc(function ($product) use ($topSellingIds) {
            return (int) ($topSellingIds[$product->id] ?? 0);
        })
        ->map(function ($product) use ($topSellingIds) {
            $formatted = $this->formatProduct($product);

            $formatted['sold_count'] = (int) (
                $topSellingIds[$product->id] ?? 0
            );

            return $formatted;
        })
        ->values();
}

        /*
        |--------------------------------------------------------------------------
        | Recommended Products
        |--------------------------------------------------------------------------
        |
        | Recommendations are based on categories the buyer has recently
        | viewed. If there is not enough history, we fall back to popular
        | products.
        |
        */
        $recentCategoryIds = $user
            ? RecentlyViewedProduct::query()
                ->where('user_id', $user->id)
                ->with('product:id,category_id')
                ->latest()
                ->get()
                ->pluck('product.category_id')
                ->filter()
                ->unique()
                ->values()
            : collect();

        $recommendedProducts = collect();

        if ($recentCategoryIds->isNotEmpty()) {
            $recommendedProducts = (clone $approvedProductQuery)
                ->whereIn('category_id', $recentCategoryIds)
                ->where('stock', '>', 0)
                ->when(
                    $featuredProducts->isNotEmpty(),
                    fn ($query) => $query->whereNotIn(
                        'id',
                        $featuredProducts->pluck('id')
                    )
                )
                ->latest('updated_at')
                ->take(8)
                ->get()
                ->map(fn ($product) => $this->formatProduct($product))
                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Popular fallback for recommendations
        |--------------------------------------------------------------------------
        */
        if ($recommendedProducts->count() < 8) {
            $existingIds = $recommendedProducts
                ->pluck('id')
                ->merge($featuredProducts->pluck('id'))
                ->unique()
                ->values();

            $popularQuery = (clone $approvedProductQuery)
                ->where('stock', '>', 0);

            if ($existingIds->isNotEmpty()) {
                $popularQuery->whereNotIn('id', $existingIds);
            }

            $popularProducts = $popularQuery
                ->withSum([
                    'orderItems as sold_count' => function ($query) {
                        $query->where('status', '!=', 'cancelled');
                    },
                ], 'quantity')
                ->orderByDesc('sold_count')
                ->latest('updated_at')
                ->take(8 - $recommendedProducts->count())
                ->get()
                ->map(fn ($product) => $this->formatProduct($product))
                ->values();

            $recommendedProducts = $recommendedProducts
                ->concat($popularProducts)
                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Active Promotions / Vouchers
        |--------------------------------------------------------------------------
        |
        | Both platform vouchers (seller_id = null) and seller vouchers
        | are shown.
        |
        */
        $today = now()->startOfDay();

        $claimedVoucherIds = $user
            ? $user->claimedVouchers()->pluck('vouchers.id')->toArray()
            : [];

        $promotions = Voucher::query()
            ->with([
                'seller:id,name',
            ])
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->where(function ($query) {
                $query
                    ->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->orderByRaw(
                'CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END'
            )
            ->orderBy('expires_at')
            ->take(8)
            ->get()
            ->map(function ($voucher) use ($claimedVoucherIds) {
                return [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'type' => $voucher->type,
                    'value' => (float) $voucher->value,
                    'min_spend' => (float) ($voucher->min_spend ?? 0),
                    'starts_at' => $voucher->starts_at?->format('Y-m-d'),
                    'expires_at' => $voucher->expires_at?->format('Y-m-d'),
                    'is_claimed' => in_array($voucher->id, $claimedVoucherIds),
                    'is_platform' => (bool) ($voucher->is_platform || $voucher->seller_id === null),
                    'seller' => $voucher->seller
                        ? [
                            'id' => $voucher->seller->id,
                            'name' => $voucher->seller->name,
                        ]
                        : null,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Recently Viewed Products
        |--------------------------------------------------------------------------
        */
        $recentlyViewedProducts = $user
            ? RecentlyViewedProduct::query()
                ->where('user_id', $user->id)
                ->with([
                    'product' => function ($query) {
                        $query
                            ->where('status', 'approved')
                            ->with([
                                'category:id,name,slug',
                                'seller:id,name',
                                'images',
                            ]);
                    },
                ])
                ->latest()
                ->take(8)
                ->get()
                ->pluck('product')
                ->filter()
                ->unique('id')
                ->values()
                ->map(fn ($product) => $this->formatProduct($product))
                ->values()
            : collect();

        /*
        |--------------------------------------------------------------------------
        | Dashboard fallback products
        |--------------------------------------------------------------------------
        |
        | Keep a general product collection for compatibility with the
        | current Dashboard.vue.
        |
        */
        $products = (clone $approvedProductQuery)
            ->where('stock', '>', 0)
            ->latest()
            ->take(12)
            ->get()
            ->map(fn ($product) => $this->formatProduct($product))
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Marketplace statistics (real data)
        |--------------------------------------------------------------------------
        */
        $marketplaceStats = [
            'products' => Product::where('status', 'approved')->count(),
            'sellers' => Product::where('status', 'approved')
                ->distinct()
                ->count('seller_id'),
            'categories' => $categories->count(),
            'orders_delivered' => (int) DB::table('order_items')
                ->whereIn('status', ['delivered', 'completed'])
                ->count(),
        ];

        return Inertia::render($guest ? 'Guest/Home' : 'Buyer/Dashboard', [
            'categories' => $categories,

            'products' => $products,

            'featuredProducts' => $featuredProducts,

            'recommendedProducts' => $recommendedProducts,

            'topSellingProducts' => $topSellingProducts,

            'promotions' => $promotions,

            'recentlyViewedProducts' => $recentlyViewedProducts,

            'marketplaceStats' => $marketplaceStats,
        ]);
    }

    /**
     * Normalize a product for the Buyer Dashboard.
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,

            'description' => $product->description,

            'price' => (float) $product->price,
            'old_price' => $product->old_price !== null
                ? (float) $product->old_price
                : null,

            'stock' => (int) $product->stock,

            'image_path' => $product->image_path,

            'is_featured' => (bool) $product->is_featured,

            'rating' => $product->rating !== null
                ? (float) $product->rating
                : 0,

            'reviews_count' => (int) (
                $product->reviews_count ?? 0
            ),

            'category' => $product->category
                ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ]
                : null,

            'seller' => $product->seller
                ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->name,
                ]
                : null,

            'images' => $product->images
                ->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_path' => $image->image_path
                            ?? $image->path
                            ?? $image->url
                            ?? null,
                    ];
                })
                ->values()
                ->all(),

            'sold_count' => (int) (
                $product->sold_count ?? 0
            ),
        ];
    }
}