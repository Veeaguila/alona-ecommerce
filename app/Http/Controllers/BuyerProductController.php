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
        $search = trim((string) $request->input('search', ''));
        $category = $request->input('category', 'all');
        $maxPrice = $request->input('max_price', 5000);
        $sort = $request->input('sort', 'featured');

        $products = Product::query()
            ->with('category:id,name,slug')
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
                        ->orWhere('description', 'like', "%{$search}%");
                })
            )

            /*
             |--------------------------------------------------------------------------
             | Category
             |--------------------------------------------------------------------------
             */
            ->when(
                $category !== 'all',
                fn ($query) => $query->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery->where(
                        'slug',
                        $category
                    )
                )
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

            default:
                $products->latest();
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
                'max_price' => (int) $maxPrice,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Display a single approved product.
     */
    public function show(Product $product): Response
    {
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

            'variants:id,product_id,color,size,stock',

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
        ];

        return Inertia::render('Buyer/Show', [
            'product' => $product,
            'sellerStats' => $sellerStats,
        ]);
    }
}