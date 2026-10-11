<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BuyerCategoryController extends Controller
{
    /**
     * Display all active categories.
     */
    public function index(): Response
    {
        $categories = Category::query()
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
                'image_path',
            ]);

        return Inertia::render('Buyer/Categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Display stores and recommended products for a specific category.
     * Supports sorting by: popular, latest, top_sales, price_low, price_high.
     */
    public function show(Request $request, Category $category): Response
    {
        abort_unless($category->is_active, 404);

        $sort = $request->input('sort', 'popular');
        $search = trim((string) $request->input('search', ''));

        // Subquery for actual sold count per product from order items (excluding cancelled)
        $soldCountSubquery = DB::table('order_items')
            ->selectRaw('COALESCE(SUM(quantity), 0)')
            ->whereColumn('order_items.product_id', 'products.id')
            ->where('order_items.status', '!=', 'cancelled');

        /*
        |--------------------------------------------------------------------------
        | 1. Stores related to this category
        |--------------------------------------------------------------------------
        | Sellers who have approved products in this category.
        */
        $stores = User::query()
            ->where('usertype', 'seller')
            ->whereIn('status', ['active', 'approved'])
            ->where(function ($query) use ($category) {
                $query->whereHas('products', function ($q) use ($category) {
                    $q->where('category_id', $category->id)
                      ->where('status', 'approved');
                })
                ->orWhere('line_of_business', $category->name);
            })
            ->withCount([
                'products as category_products_count' => function ($q) use ($category) {
                    $q->where('category_id', $category->id)
                      ->where('status', 'approved');
                },
                'products as total_products_count' => function ($q) {
                    $q->where('status', 'approved');
                },
            ])
            ->get([
                'id',
                'name',
                'store_name',
                'store_description',
                'store_logo_path',
                'shipping_fee',
                'return_policy_days',
            ])
            ->map(function ($seller) use ($category) {
                // Calculate seller rating & total reviews
                $sellerApprovedProducts = Product::where('seller_id', $seller->id)
                    ->where('status', 'approved');

                $avgRating = (clone $sellerApprovedProducts)->whereNotNull('rating')->avg('rating');
                $totalReviews = (clone $sellerApprovedProducts)->sum('reviews_count');

                // Get up to 3 preview products from this seller in this category
                $sampleProducts = Product::where('seller_id', $seller->id)
                    ->where('category_id', $category->id)
                    ->where('status', 'approved')
                    ->latest('id')
                    ->take(3)
                    ->get(['id', 'name', 'price', 'image_path']);

                return [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'store_name' => $seller->store_name ?: $seller->name,
                    'store_description' => $seller->store_description,
                    'store_logo_path' => $seller->store_logo_path,
                    'shipping_fee' => (float) ($seller->shipping_fee ?? 0),
                    'return_policy_days' => (int) ($seller->return_policy_days ?? 7),
                    'category_products_count' => (int) $seller->category_products_count,
                    'total_products_count' => (int) $seller->total_products_count,
                    'avg_rating' => $avgRating ? round((float) $avgRating, 1) : null,
                    'total_reviews' => (int) $totalReviews,
                    'sample_products' => $sampleProducts,
                ];
            });

        // Only actual stores with approved products in this category are shown.

        /*
        |--------------------------------------------------------------------------
        | 2. Recommended / Products Query for this category
        |--------------------------------------------------------------------------
        */
        $productsQuery = Product::query()
            ->with([
                'category:id,name,slug',
                'seller:id,name,store_name,store_logo_path',
            ])
            ->select('products.*')
            ->selectSub($soldCountSubquery, 'sold_count')
            ->where('category_id', $category->id)
            ->where('status', 'approved')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        /*
        |--------------------------------------------------------------------------
        | 3. Sorting options:
        | - popular: high rating, review count, and sales combined
        | - latest: newly created / updated
        | - top_sales: highest units sold
        | - price_low: lowest price first
        | - price_high: highest price first
        |--------------------------------------------------------------------------
        */
        switch ($sort) {
            case 'latest':
                $productsQuery->latest('id');
                break;

            case 'top_sales':
                $productsQuery->orderByDesc('sold_count')->orderByDesc('id');
                break;

            case 'price_low':
                $productsQuery->orderBy('price', 'asc');
                break;

            case 'price_high':
                $productsQuery->orderBy('price', 'desc');
                break;

            case 'popular':
            default:
                $productsQuery->orderByDesc('rating')
                    ->orderByDesc('sold_count')
                    ->orderByDesc('reviews_count')
                    ->latest('id');
                break;
        }

        $products = $productsQuery
            ->paginate(15)
            ->withQueryString();

        // Also fetch sibling categories for quick switching
        $allCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image_path']);

        return Inertia::render('Buyer/CategoryShow', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description ?? null,
                'image_path' => $category->image_path,
            ],
            'stores' => $stores,
            'products' => $products,
            'allCategories' => $allCategories,
            'filters' => [
                'sort' => $sort,
                'search' => $search,
            ],
        ]);
    }
}