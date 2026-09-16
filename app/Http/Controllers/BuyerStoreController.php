<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerStoreController extends Controller
{
    public function show(Request $request, User $seller): Response
    {
        abort_unless($seller->usertype === 'seller' && $seller->status === 'active', 404);

        $sort = $request->input('sort', 'latest');
        $search = trim((string) $request->input('search', ''));

        $products = Product::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                })
            );

        switch ($sort) {
            case 'price-low':
                $products->orderBy('price', 'asc');
                break;
            case 'price-high':
                $products->orderBy('price', 'desc');
                break;
            case 'rating':
                $products->orderByDesc('rating')->orderByDesc('reviews_count');
                break;
            default:
                $products->latest();
                break;
        }

        $products = $products->paginate(12)->withQueryString();

        // Calculate seller stats
        $totalProducts = Product::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->count();

        $avgRating = Product::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->whereNotNull('rating')
            ->avg('rating');

        $totalReviews = Product::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->sum('reviews_count');

        return Inertia::render('Buyer/SellerStore', [
            'seller' => $seller->only([
                'id', 'name', 'store_name', 'store_description',
                'store_logo_path', 'shipping_fee', 'return_policy_days'
            ]),
            'products' => $products,
            'stats' => [
                'total_products' => $totalProducts,
                'avg_rating' => $avgRating ? round($avgRating, 2) : null,
                'total_reviews' => $totalReviews,
            ],
            'filters' => [
                'search' => $search,
                'sort' => $sort,
            ],
        ]);
    }
}
