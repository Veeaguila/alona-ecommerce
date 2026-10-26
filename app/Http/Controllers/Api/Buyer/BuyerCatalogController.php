<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Models\Review;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuyerCatalogController extends Controller
{
    /**
     * Marketplace Home / Dashboard Data.
     */
    public function home(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        // Approved products query
        $approvedQuery = Product::query()
            ->where('status', 'approved')
            ->with(['category:id,name,slug', 'seller:id,name,store_name,store_logo_path', 'images']);

        // Categories
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image_path']);

        // Featured Products
        $featured = (clone $approvedQuery)
            ->where('is_featured', true)
            ->where('stock', '>', 0)
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(fn ($p) => $this->formatProductItem($p));

        // Top Selling Products
        $topSellingIds = DB::table('order_items')
            ->select('product_id', DB::raw('SUM(quantity) as sold_count'))
            ->whereNotNull('product_id')
            ->where('status', '!=', 'cancelled')
            ->groupBy('product_id')
            ->orderByDesc('sold_count')
            ->limit(12)
            ->pluck('sold_count', 'product_id');

        $topSelling = collect();
        if ($topSellingIds->isNotEmpty()) {
            $topSelling = (clone $approvedQuery)
                ->whereIn('id', $topSellingIds->keys())
                ->get()
                ->sortByDesc(fn ($p) => (int) ($topSellingIds[$p->id] ?? 0))
                ->map(function ($p) use ($topSellingIds) {
                    $item = $this->formatProductItem($p);
                    $item['sold_count'] = (int) ($topSellingIds[$p->id] ?? 0);
                    return $item;
                })
                ->values();
        }

        // Recommended Products
        $recentCategoryIds = $user
            ? RecentlyViewedProduct::query()
                ->where('user_id', $user->id)
                ->with('product:id,category_id')
                ->latest()
                ->take(10)
                ->get()
                ->pluck('product.category_id')
                ->filter()
                ->unique()
                ->values()
            : collect();

        $recommended = collect();
        if ($recentCategoryIds->isNotEmpty()) {
            $recommended = (clone $approvedQuery)
                ->whereIn('category_id', $recentCategoryIds)
                ->where('stock', '>', 0)
                ->latest('updated_at')
                ->take(8)
                ->get()
                ->map(fn ($p) => $this->formatProductItem($p));
        }

        if ($recommended->count() < 8) {
            $existing = $recommended->pluck('id')->merge($featured->pluck('id'))->unique();
            $more = (clone $approvedQuery)
                ->where('stock', '>', 0)
                ->when($existing->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $existing))
                ->latest('updated_at')
                ->take(8 - $recommended->count())
                ->get()
                ->map(fn ($p) => $this->formatProductItem($p));
            $recommended = $recommended->concat($more);
        }

        // Active Flash Sales & Countdown
        $flashSales = [];
        $flashSaleEndTime = null;

        if (Schema::hasTable('flash_sales')) {
            $now = now();
            $activeSales = FlashSale::query()
                ->with([
                    'product' => fn ($q) => $q->where('status', 'approved')->with(['category:id,name,slug', 'seller:id,name', 'images']),
                ])
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->whereNull('ended_at')
                ->where('flash_quantity', '>', 0)
                ->orderBy('ends_at', 'asc')
                ->take(8)
                ->get();

            $earliestEnd = $activeSales->filter(fn ($s) => $s->ends_at !== null)->min('ends_at');
            if ($earliestEnd) {
                $flashSaleEndTime = \Carbon\Carbon::parse($earliestEnd)->toIso8601String();
            }

            $flashSales = $activeSales->map(function ($sale) {
                $p = $sale->product;
                if (! $p) return null;
                $origPrice = (float) $p->price;
                $fPrice = (float) $sale->flash_price;
                $discountPercent = $origPrice > 0 ? (int) round((($origPrice - $fPrice) / $origPrice) * 100) : 0;
                $claimed = (int) $sale->sold_quantity;
                $total = (int) $sale->flash_quantity;

                return [
                    'id' => $sale->id,
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'image_url' => $this->resolveProductImage($p),
                    'flash_price' => $fPrice,
                    'original_price' => $origPrice,
                    'discount_percent' => max(0, $discountPercent),
                    'sold_quantity' => $claimed,
                    'flash_quantity' => $total,
                    'progress_percent' => $total > 0 ? min(100, (int) round(($claimed / $total) * 100)) : 0,
                    'remaining' => max(0, $total - $claimed),
                    'ends_at' => $sale->ends_at?->toIso8601String(),
                ];
            })->filter()->values();
        }

        // Active Promotions / Vouchers
        $today = now()->startOfDay();
        $promotions = Voucher::query()
            ->with('seller:id,name')
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today);
            })
            ->take(8)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'code' => $v->code,
                'type' => $v->type,
                'value' => (float) $v->value,
                'min_spend' => (float) ($v->min_spend ?? 0),
                'expires_at' => $v->expires_at?->format('Y-m-d'),
                'is_platform' => (bool) ($v->is_platform || $v->seller_id === null),
                'seller_name' => $v->seller?->name ?? 'Platform',
            ]);

        // Trending Products for "People are looking"
        $trending = (clone $approvedQuery)
            ->where('stock', '>', 0)
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(fn ($p) => $this->formatProductItem($p));

        return response()->json([
            'categories' => $categories,
            'featured_products' => $featured,
            'top_selling_products' => $topSelling,
            'recommended_products' => $recommended,
            'trending_products' => $trending,
            'flash_sales' => $flashSales,
            'flash_sale_end_time' => $flashSaleEndTime,
            'promotions' => $promotions,
        ]);
    }

    /**
     * Search and Filter Product Catalog.
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('status', 'approved')
            ->with(['category:id,name,slug', 'seller:id,name,store_name', 'images', 'variants']);

        // Search
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        } elseif ($categorySlug = $request->input('category_slug')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        // Seller filter
        if ($sellerId = $request->input('seller_id')) {
            $query->where('seller_id', $sellerId);
        }

        // Price range
        if ($minPrice = $request->input('min_price')) {
            $query->where('price', '>=', (float) $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'price_low' => $query->orderBy('price', 'asc'),
            'price_high' => $query->orderBy('price', 'desc'),
            'rating' => $query->orderByDesc('rating'),
            'top_selling' => $query->withSum(['orderItems as sold_total' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'quantity')->orderByDesc('sold_total'),
            default => $query->latest('id'),
        };

        $perPage = max(10, min(50, (int) $request->input('per_page', 20)));
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn ($p) => $this->formatProductItem($p));

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Get Detailed Product Information.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $product = Product::query()
            ->where('status', 'approved')
            ->whereKey($id)
            ->with([
                'category:id,name,slug',
                'seller:id,name,store_name,store_logo_path,store_description,response_time,vacation_mode,vacation_message,return_policy_days',
                'images' => fn ($q) => $q->orderBy('sort_order'),
                'variants',
                'reviews' => fn ($q) => $q->with('user:id,name,avatar')->latest()->take(10),
            ])
            ->firstOrFail();

        // Seller Statistics
        $sellerStats = [
            'total_products' => $product->seller_id ? Product::where('seller_id', $product->seller_id)->where('status', 'approved')->count() : 0,
            'avg_rating' => $product->seller_id ? (float) (Product::where('seller_id', $product->seller_id)->where('status', 'approved')->whereNotNull('rating')->avg('rating') ?: 4.9) : 4.9,
            'followers_count' => ($product->seller_id && Schema::hasTable('store_followers')) ? \App\Models\StoreFollower::where('seller_id', $product->seller_id)->count() : 0,
        ];

        // Store Vouchers
        $vouchers = collect();
        if ($product->seller_id && Schema::hasTable('vouchers')) {
            $today = now()->toDateString();
            $vouchers = \App\Models\Voucher::query()
                ->where('seller_id', $product->seller_id)
                ->where('is_active', true)
                ->when(Schema::hasColumn('vouchers', 'ended_at'), fn ($q) => $q->whereNull('ended_at'))
                ->when(Schema::hasColumn('vouchers', 'display_mode'), function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('display_mode')->orWhere('display_mode', '!=', 'code_only');
                    });
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today);
                })
                ->where(function ($q) {
                    $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
                })
                ->latest()
                ->get()
                ->filter(fn ($v) => method_exists($v, 'appliesToProduct') ? $v->appliesToProduct((int) $product->id, (int) $product->seller_id) : true)
                ->values();
        }

        // Record recently viewed & user-specific checks if authenticated
        $user = $request->user('sanctum');
        $claimedVoucherIds = [];
        $inWishlist = false;
        $isFollowingStore = false;
        $userPurchasedCount = 0;
        $userRemainingLimit = null;

        if ($user) {
            RecentlyViewedProduct::updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $product->id],
                ['updated_at' => now()]
            );

            if (Schema::hasTable('user_vouchers') && $vouchers->isNotEmpty()) {
                $claimedVoucherIds = \App\Models\UserVoucher::where('user_id', $user->id)
                    ->whereIn('voucher_id', $vouchers->pluck('id'))
                    ->where('status', 'claimed')
                    ->pluck('voucher_id')
                    ->toArray();
            }

            if (Schema::hasTable('wishlists')) {
                $inWishlist = \App\Models\Wishlist::where('user_id', $user->id)
                    ->where('product_id', $product->id)
                    ->exists();
            }

            if ($product->seller_id && Schema::hasTable('store_followers')) {
                $isFollowingStore = \App\Models\StoreFollower::where('user_id', $user->id)
                    ->where('seller_id', $product->seller_id)
                    ->exists();
            }

            if (method_exists($product, 'purchasedUnitsCountFor')) {
                $userPurchasedCount = $product->purchasedUnitsCountFor($user);
            }
            if (method_exists($product, 'remainingPurchaseLimitFor')) {
                $userRemainingLimit = $product->remainingPurchaseLimitFor($user);
            }
        }

        // Related products in the same category
        $related = Product::query()
            ->where('status', 'approved')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('stock', '>', 0)
            ->take(6)
            ->get()
            ->map(fn ($p) => $this->formatProductItem($p));

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price' => (float) $product->price,
                'old_price' => $product->old_price ? (float) $product->old_price : null,
                'stock' => (int) $product->stock,
                'rating' => (float) ($product->rating ?? 0),
                'reviews_count' => (int) ($product->reviews_count ?? 0),
                'brand' => $product->brand,
                'condition' => $product->condition ?? 'Brand New',
                'per_buyer_limit' => $product->per_buyer_limit ? (int) $product->per_buyer_limit : null,
                'is_featured' => (bool) $product->is_featured,
                'has_active_flash_sale' => (bool) $product->has_active_flash_sale,
                'flash_price' => $product->flash_price ? (float) $product->flash_price : null,
                'flash_ends_at' => $product->flash_ends_at,
                'flash_remaining' => $product->flash_remaining,
                'flash_countdown_seconds' => $product->flash_countdown_seconds,
                'image_url' => $this->resolveProductImage($product),
                'images' => $product->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/' . $img->image_path),
                ]),
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'color' => $v->color,
                    'size' => $v->size,
                    'price' => (float) $v->price,
                    'stock' => (int) $v->stock,
                    'sku' => $v->sku,
                    'image_url' => $v->image_path ? asset('storage/' . $v->image_path) : null,
                ]),
                'category' => $product->category,
                'seller' => [
                    'id' => $product->seller?->id,
                    'name' => $product->seller?->store_name ?: $product->seller?->name,
                    'store_name' => $product->seller?->store_name,
                    'store_description' => $product->seller?->store_description,
                    'logo_url' => $product->seller?->store_logo_path ? asset('storage/' . $product->seller->store_logo_path) : null,
                    'response_time' => $product->seller?->response_time ?? 'Within a few hours',
                    'rating' => $sellerStats['avg_rating'],
                    'total_products' => $sellerStats['total_products'],
                    'followers_count' => $sellerStats['followers_count'],
                    'is_on_vacation' => (bool) ($product->seller?->vacation_mode ?? false),
                    'vacation_message' => $product->seller?->vacation_message,
                ],
                'vouchers' => $vouchers->map(fn ($v) => [
                    'id' => $v->id,
                    'code' => $v->code,
                    'claim_label' => $v->claim_label ?? ('₱' . number_format($v->value, 0) . ' OFF'),
                    'value' => (float) $v->value,
                    'min_spend' => (float) ($v->min_spend ?? 0),
                    'is_claimed' => in_array($v->id, $claimedVoucherIds),
                ]),
                'claimed_voucher_ids' => $claimedVoucherIds,
                'in_wishlist' => $inWishlist,
                'is_following_store' => $isFollowingStore,
                'user_purchased_count' => $userPurchasedCount,
                'user_remaining_limit' => $userRemainingLimit,
                'reviews' => $product->reviews->map(fn ($r) => [
                    'id' => $r->id,
                    'rating' => (int) $r->rating,
                    'comment' => $r->comment,
                    'created_at' => $r->created_at?->format('M d, Y'),
                    'user_name' => $r->user?->name ?? 'Verified Buyer',
                    'avatar_url' => $r->user?->avatar ? asset('storage/' . $r->user->avatar) : null,
                    'seller_reply' => $r->seller_reply,
                    'seller_replied_at' => $r->seller_replied_at?->format('M d, Y'),
                ]),
            ],
            'related_products' => $related,
        ]);
    }

    /**
     * Categories Listing.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with([
                'activeSubcategories' => fn ($q) =>
                    $q->select(['id', 'parent_id', 'name', 'slug', 'image_path'])
                      ->withCount(['products' => fn ($pq) => $pq->where('status', 'approved')])
                      ->orderBy('name'),
            ])
            ->withCount(['products' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image_path']);

        return response()->json(['categories' => $categories]);
    }

    private function formatProductItem(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => (float) $p->price,
            'old_price' => $p->old_price ? (float) $p->old_price : null,
            'stock' => (int) $p->stock,
            'rating' => (float) ($p->rating ?? 0),
            'reviews_count' => (int) ($p->reviews_count ?? 0),
            'image_url' => $this->resolveProductImage($p),
            'category_name' => $p->category?->name,
            'seller_name' => $p->seller?->store_name ?: $p->seller?->name,
            'has_flash_sale' => (bool) $p->has_active_flash_sale,
            'flash_price' => $p->flash_price ? (float) $p->flash_price : null,
        ];
    }

    private function resolveProductImage(Product $p): string
    {
        if ($p->image_path) {
            return asset('storage/' . $p->image_path);
        }
        $firstImage = $p->images?->first();
        if ($firstImage && $firstImage->image_path) {
            return asset('storage/' . $firstImage->image_path);
        }
        return asset('images/alona.png');
    }
}

