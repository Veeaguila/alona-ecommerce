<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\UserVoucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerStoreController extends Controller
{
    public function show(Request $request, User $seller): Response
    {
        abort_unless(
            $seller->usertype === 'seller' &&
            in_array($seller->status, ['active', 'approved'], true),
            404
        );

        $sort = $request->input('sort', 'latest');
        $search = trim((string) $request->input('search', ''));
        $categorySlug = $request->input('category') ?: $request->input('store_category');

        $productsQuery = Product::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                })
            );

        if ($categorySlug && $categorySlug !== 'all') {
            $matchedStoreCategory = \App\Models\StoreCategory::query()
                ->where('seller_id', $seller->id)
                ->where(function ($q) use ($categorySlug) {
                    $q->where('slug', $categorySlug)
                      ->orWhere('name', $categorySlug)
                      ->orWhere('id', $categorySlug);
                })
                ->first();

            if ($matchedStoreCategory) {
                // SEL-SCAT-007: Hidden store category does not expose products on direct URL / 404s
                if (! $matchedStoreCategory->is_visible) {
                    abort(404, 'Category not found or unavailable');
                }

                $catIds = array_merge(
                    [$matchedStoreCategory->id],
                    $matchedStoreCategory->children()->where('is_visible', true)->pluck('id')->toArray()
                );

                $productsQuery->whereHas('storeCategories', fn ($q) => $q->whereIn('store_categories.id', $catIds));
            } else {
                $productsQuery->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            }
        }

        switch ($sort) {
            case 'price-low':
                $productsQuery->orderBy('price', 'asc');
                break;
            case 'price-high':
                $productsQuery->orderBy('price', 'desc');
                break;
            case 'rating':
                $productsQuery->orderByDesc('rating')->orderByDesc('reviews_count');
                break;
            default:
                $productsQuery->latest();
                break;
        }

        $products = (clone $productsQuery)->paginate(12)->withQueryString();

        // Recommended / Featured Products
        $recommendedProducts = Product::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->where('is_featured', true)
            ->limit(6)
            ->get();

        if ($recommendedProducts->isEmpty()) {
            $recommendedProducts = Product::query()
                ->where('seller_id', $seller->id)
                ->where('status', 'approved')
                ->orderByDesc('rating')
                ->orderByDesc('reviews_count')
                ->limit(6)
                ->get();
        }

        // Calculate seller stats
        $totalProducts = Product::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->count();

        // Product reviews & ratings
        $productReviewStats = Product::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->whereNotNull('rating')
            ->selectRaw('AVG(rating) as avg_rating, SUM(reviews_count) as total_reviews')
            ->first();

        // Direct seller reviews
        $sellerReviewStats = $seller->sellerReviewsReceived()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_reviews')
            ->first();

        $totalReviews = (int) (($productReviewStats->total_reviews ?? 0) + ($sellerReviewStats->total_reviews ?? 0));

        $avgRating = null;
        if ($sellerReviewStats && $sellerReviewStats->total_reviews > 0 && $productReviewStats && $productReviewStats->total_reviews > 0) {
            $avgRating = round(
                (($sellerReviewStats->avg_rating * $sellerReviewStats->total_reviews) + ($productReviewStats->avg_rating * $productReviewStats->total_reviews)) / $totalReviews,
                1
            );
        } elseif ($sellerReviewStats && $sellerReviewStats->total_reviews > 0) {
            $avgRating = round((float) $sellerReviewStats->avg_rating, 1);
        } elseif ($productReviewStats && $productReviewStats->total_reviews > 0) {
            $avgRating = round((float) $productReviewStats->avg_rating, 1);
        } elseif ($productReviewStats && $productReviewStats->avg_rating) {
            $avgRating = round((float) $productReviewStats->avg_rating, 1);
        }

        // Store categories: custom seller store categories (SEL-SCAT-001, SEL-SCAT-007, SEL-SCAT-008)
        $sellerStoreCategories = \App\Models\StoreCategory::query()
            ->where('seller_id', $seller->id)
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        if ($sellerStoreCategories->isNotEmpty()) {
            $categories = $sellerStoreCategories;
        } else {
            // Fallback to platform categories of active seller products
            $categories = Product::query()
                ->where('seller_id', $seller->id)
                ->where('status', 'approved')
                ->with('category:id,name,slug')
                ->get()
                ->pluck('category')
                ->filter()
                ->unique('id')
                ->values();
        }

        // Authentic store vouchers (SEL-VOU-001, SEL-VOU-003: exclude code-only vouchers)
        $vouchers = Voucher::query()
            ->where('seller_id', $seller->id)
            ->where(function ($q) {
                $q->where('is_active', true)
                  ->orWhereNull('is_active');
            })
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
            ->get();

        $claimedVoucherIds = [];
        if ($request->user()) {
            $claimedVoucherIds = UserVoucher::where('user_id', $request->user()->id)
                ->whereIn('voucher_id', $vouchers->pluck('id'))
                ->where('status', 'claimed')
                ->pluck('voucher_id')
                ->toArray();
        }

        // Formatted joined duration
        $joinedAgo = $seller->created_at
            ? Carbon::parse($seller->created_at)->diffForHumans(null, true) . ' ago'
            : null;

        // Store followers calculation
        $followersCount = \App\Models\StoreFollower::where('seller_id', $seller->id)->count();
        $isFollowing = false;
        if ($request->user()) {
            $isFollowing = \App\Models\StoreFollower::where('seller_id', $seller->id)
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        return Inertia::render('Buyer/SellerStore', [
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'store_name' => $seller->store_name ?: $seller->name,
                'store_description' => $seller->store_description,
                'store_logo_path' => $seller->store_logo_path,
                'store_banner_path' => $seller->store_banner_path,
                'store_tagline' => $seller->store_tagline,
                'operating_hours' => $seller->operating_hours ?: \App\Models\User::getDefaultOperatingHours(),
                'response_time' => $seller->response_time ?: 'within_day',
                'response_time_label' => $seller->getResponseTimeLabel(),
                'store_status' => $seller->getStoreStatus(),
                'shipping_policy' => $seller->shipping_policy,
                'warranty_policy' => $seller->warranty_policy,
                'return_policy' => $seller->return_policy,
                'vacation_mode' => (bool) $seller->vacation_mode,
                'vacation_message' => $seller->vacation_message,
                'shipping_fee' => $seller->shipping_fee !== null ? (float) $seller->shipping_fee : null,
                'return_policy_days' => $seller->return_policy_days ? (int) $seller->return_policy_days : 7,
                'line_of_business' => $seller->line_of_business,
                'has_business_permit' => !empty($seller->business_permit_path),
                'business_permit_path' => $seller->business_permit_path,
                'created_at' => $seller->created_at,
                'status' => $seller->status,
            ],
            'products' => $products,
            'recommended_products' => $recommendedProducts,
            'categories' => $categories,
            'vouchers' => $vouchers,
            'claimed_voucher_ids' => $claimedVoucherIds,
            'is_following' => $isFollowing,
            'stats' => [
                'total_products' => $totalProducts,
                'avg_rating' => $avgRating,
                'total_reviews' => $totalReviews,
                'followers_count' => $followersCount,
                'joined' => $joinedAgo,
                'has_bir' => !empty($seller->business_permit_path),
                'return_policy_days' => $seller->return_policy_days ? (int) $seller->return_policy_days : 7,
                'shipping_fee' => $seller->shipping_fee !== null ? (float) $seller->shipping_fee : null,
            ],
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'category' => $categorySlug,
            ],
        ]);
    }

    /**
     * Toggle following a seller store (BUY-WISH-004 & BUY-WISH-005).
     */
    public function toggleFollow(Request $request, User $seller)
    {
        abort_unless(
            $seller->usertype === 'seller' &&
            in_array($seller->status, ['active', 'approved'], true),
            404
        );

        if (!auth()->check()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('login'));
        }

        $user = $request->user();

        if ($user->id === $seller->id) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'You cannot follow your own store.'], 422);
            }
            return back()->with('error', 'You cannot follow your own store.');
        }

        $existing = \App\Models\StoreFollower::where('user_id', $user->id)
            ->where('seller_id', $seller->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $isFollowing = false;
            $message = 'Unfollowed store successfully.';
        } else {
            \App\Models\StoreFollower::firstOrCreate([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
            ]);
            $isFollowing = true;
            $message = 'Following store successfully.';
        }

        $followersCount = \App\Models\StoreFollower::where('seller_id', $seller->id)->count();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_following' => $isFollowing,
                'followers_count' => $followersCount,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
