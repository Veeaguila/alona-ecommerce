<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $category = $request->input('category', 'all');
        $maxPrice = $request->input('max_price', 5000);
        $sort = $request->input('sort', 'featured');

        $products = Product::query()
            ->with('category:id,name,slug')
            ->where('status', 'approved')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
            )
            ->when(
                $category !== 'all',
                fn ($query) => $query->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery->where('slug', $category)
                )
            )
            ->when(
                is_numeric($maxPrice),
                fn ($query) => $query->where(
                    'price',
                    '<=',
                    (float) $maxPrice
                )
            );

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
                ->get(['name', 'slug']),

            'filters' => [
                'search' => $search,
                'category' => $category,
                'max_price' => (int) $maxPrice,
                'sort' => $sort,
            ],
        ]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->status === 'approved', 404);

        $product->load([
            'category:id,name,slug',

            'seller:id,name,store_name,store_description,store_logo_path,return_policy_days',

            'variants:id,product_id,color,size,stock',

            'reviews' => fn ($q) => $q
                ->with('user:id,name')
                ->latest(),

            'questions' => fn ($q) => $q
                ->where('is_public', true)
                ->whereNotNull('answer')
                ->with(
                    'buyer:id,name',
                    'answeredBy:id,name'
                )
                ->latest()
                ->limit(5),
        ]);

        return Inertia::render('Buyer/Show', [
            'product' => $product,

            'sellerStats' => [
                'total_products' => \App\Models\Product::where(
                    'seller_id',
                    $product->seller_id
                )
                    ->where('status', 'approved')
                    ->count(),

                'avg_rating' => \App\Models\Product::where(
                    'seller_id',
                    $product->seller_id
                )
                    ->where('status', 'approved')
                    ->whereNotNull('rating')
                    ->avg('rating'),
            ],
        ]);
    }
}