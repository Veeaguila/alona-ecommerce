<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerReviewController extends Controller
{
    /**
     * Show the buyer's review history.
     */
    public function index(Request $request): Response
    {
        $reviews = Review::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'product:id,name,image_path',
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Buyer/Reviews', [
            'reviews' => $reviews,
        ]);
    }

    /**
     * Store a product review.
     */
    public function store(
        Request $request,
        Product $product
    ): RedirectResponse {
        $validated = $request->validate([
            'rating' => [
                'required',
                'integer',
                'min:1',
                'max:5',
            ],

            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Make sure the buyer actually purchased the product
        |--------------------------------------------------------------------------
        */

        $eligibleOrderItem = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereIn('status', [
                'delivered',
                'completed',
            ])
            ->whereHas('order', function ($query) use ($request) {
                $query->where(
                    'user_id',
                    $request->user()->id
                );
            })
            ->first();

        if (!$eligibleOrderItem) {
            return back()->withErrors([
                'rating' =>
                    'You can only review products that you have received.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate reviews
        |--------------------------------------------------------------------------
        */

        $alreadyReviewed = Review::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'product_id',
                $product->id
            )
            ->exists();

        if ($alreadyReviewed) {
            return back()->withErrors([
                'rating' =>
                    'You have already reviewed this product.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create review
        |--------------------------------------------------------------------------
        */

        Review::create([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Recalculate product rating and review count
        |--------------------------------------------------------------------------
        */

        $reviewQuery = Review::query()
            ->where('product_id', $product->id);

        $reviewCount = $reviewQuery->count();

        $averageRating = $reviewCount > 0
            ? round((float) $reviewQuery->avg('rating'), 2)
            : 0;

        $product->update([
            'rating' => $averageRating,
            'reviews_count' => $reviewCount,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Return success
        |--------------------------------------------------------------------------
        */

        return back()->with(
            'success',
            'Your review has been submitted successfully.'
        );
    }

    /**
     * Show all reviews for a product.
     */
    public function productReviews(Product $product): Response
    {
        abort_unless($product->status === 'approved', 404);

        $reviews = Review::query()
            ->where('product_id', $product->id)
            ->with([
                'user:id,name',
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Buyer/ProductReviews', [
            'product' => $product->only([
                'id',
                'name',
                'image_path',
                'rating',
                'reviews_count',
            ]),
            'reviews' => $reviews,
        ]);
    }
}

