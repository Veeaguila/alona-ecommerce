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
     * Display all active categories and their subcategories.
     */
    public function index(): Response
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with([
                'activeSubcategories' => function ($q) {
                    $q->select(['id', 'parent_id', 'name', 'slug', 'image_path'])
                      ->withCount(['products' => fn ($query) => $query->where('status', 'approved')])
                      ->orderBy('name');
                },
            ])
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

        // Calculate total products per root category including all its subcategories
        $categories->each(function ($cat) {
            $subProductsTotal = $cat->activeSubcategories->sum('products_count');
            $cat->total_products_count = (int) $cat->products_count + (int) $subProductsTotal;
        });

        return Inertia::render('Buyer/Categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Display stores and recommended products for a specific category or subcategory.
     * Supports sorting by: popular, latest, top_sales, price_low, price_high.
     * Supports subcategory filtering via ?subcategory=slug.
     */
    public function show(Request $request, Category $category): Response
    {
        abort_unless($category->is_active, 404);

        $category->load(['parent:id,name,slug', 'activeSubcategories']);

        $sort = $request->input('sort', 'popular');
        $search = trim((string) $request->input('search', ''));
        $selectedSubSlug = trim((string) $request->input('subcategory', ''));

        // Determine target category IDs for filtering products & stores
        $targetCategoryIds = [$category->id];

        if ($selectedSubSlug !== '') {
            $selectedSub = Category::where('slug', $selectedSubSlug)
                ->where('parent_id', $category->id)
                ->where('is_active', true)
                ->first();

            if ($selectedSub) {
                $targetCategoryIds = [$selectedSub->id];
            } else {
                $selectedSubSlug = '';
            }
        } elseif ($category->isRoot()) {
            // Include root category products and all active subcategory products
            $targetCategoryIds = $category->getDescendantIds();
        }

        // Subquery for actual sold count per product from order items (excluding cancelled)
        $soldCountSubquery = DB::table('order_items')
            ->selectRaw('COALESCE(SUM(quantity), 0)')
            ->whereColumn('order_items.product_id', 'products.id')
            ->where('order_items.status', '!=', 'cancelled');

        /*
        |--------------------------------------------------------------------------
        | 1. Stores related to this category / subcategories
        |--------------------------------------------------------------------------
        | Sellers who have approved products in target category IDs.
        */
        $stores = User::query()
            ->where('usertype', 'seller')
            ->whereIn('status', ['active', 'approved'])
            ->where(function ($query) use ($category, $targetCategoryIds) {
                $query->whereHas('products', function ($q) use ($targetCategoryIds) {
                    $q->whereIn('category_id', $targetCategoryIds)
                      ->where('status', 'approved');
                })
                ->orWhere('line_of_business', $category->name);
            })
            ->withCount([
                'products as category_products_count' => function ($q) use ($targetCategoryIds) {
                    $q->whereIn('category_id', $targetCategoryIds)
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
            ->map(function ($seller) use ($targetCategoryIds) {
                // Calculate seller rating & total reviews
                $sellerApprovedProducts = Product::where('seller_id', $seller->id)
                    ->where('status', 'approved');

                $avgRating = (clone $sellerApprovedProducts)->whereNotNull('rating')->avg('rating');
                $totalReviews = (clone $sellerApprovedProducts)->sum('reviews_count');

                // Get up to 3 preview products from this seller in this category/subcategories
                $sampleProducts = Product::where('seller_id', $seller->id)
                    ->whereIn('category_id', $targetCategoryIds)
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

        /*
        |--------------------------------------------------------------------------
        | 2. Recommended / Products Query for this category & subcategories
        |--------------------------------------------------------------------------
        */
        $productsQuery = Product::query()
            ->with([
                'category:id,parent_id,name,slug',
                'seller:id,name,store_name,store_logo_path',
            ])
            ->select('products.*')
            ->selectSub($soldCountSubquery, 'sold_count')
            ->whereIn('category_id', $targetCategoryIds)
            ->where('status', 'approved')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        /*
        |--------------------------------------------------------------------------
        | 3. Sorting options
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

        // Fetch hierarchical categories for sidebar navigation tree
        $allCategories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with([
                'activeSubcategories' => function ($q) {
                    $q->select(['id', 'parent_id', 'name', 'slug', 'image_path'])
                      ->withCount(['products' => fn ($pq) => $pq->where('status', 'approved')])
                      ->orderBy('name');
                },
            ])
            ->withCount(['products' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image_path']);

        // Subcategories available for pill filter bar (either children of this category or sibling subcategories)
        $subcategories = $category->isRoot()
            ? $category->activeSubcategories()
                ->withCount(['products' => fn ($q) => $q->where('status', 'approved')])
                ->get(['id', 'parent_id', 'name', 'slug', 'image_path'])
            : ($category->parent
                ? $category->parent->activeSubcategories()
                    ->withCount(['products' => fn ($q) => $q->where('status', 'approved')])
                    ->get(['id', 'parent_id', 'name', 'slug', 'image_path'])
                : collect());

        return Inertia::render('Buyer/CategoryShow', [
            'category' => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description ?? null,
                'image_path' => $category->image_path,
                'parent' => $category->parent ? [
                    'id' => $category->parent->id,
                    'name' => $category->parent->name,
                    'slug' => $category->parent->slug,
                ] : null,
            ],
            'subcategories' => $subcategories,
            'selectedSubcategory' => $selectedSubSlug,
            'stores' => $stores,
            'products' => $products,
            'allCategories' => $allCategories,
            'filters' => [
                'sort' => $sort,
                'search' => $search,
                'subcategory' => $selectedSubSlug,
            ],
        ]);
    }
}
