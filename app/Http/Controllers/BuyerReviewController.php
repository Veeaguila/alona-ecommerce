<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerReview;
use App\Models\SellerNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuyerReviewController extends Controller
{
    /**
     * Show the buyer's reviews page with submitted reviews, awaiting reviews,
     * and seller ratings (BUYER-28, BUYER-30).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = strtolower((string) $request->input('tab', 'reviewed'));

        // 1. Submitted product reviews (with edit capability)
        $reviewsQuery = Review::query()
            ->where('user_id', $user->id)
            ->with([
                'product:id,name,image_path,rating,reviews_count',
            ])
            ->latest();

        // 2. Delivered products awaiting review (BUYER-28)
        // Find delivered order items whose product_id has NOT been reviewed by user
        $reviewedProductIds = Review::query()
            ->where('user_id', $user->id)
            ->pluck('product_id');

        $awaitingQuery = OrderItem::query()
            ->whereIn('status', ['delivered', 'completed'])
            ->whereHas('order', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereNotIn('product_id', $reviewedProductIds)
            ->with([
                'product:id,name,image_path,seller_id',
                'product.seller:id,name',
                'order:id,order_number,created_at',
            ])
            ->latest();

        // 3. Seller reviews & orders awaiting seller review (BUYER-30)
        $sellerReviewsQuery = SellerReview::query()
            ->where('user_id', $user->id)
            ->with([
                'seller:id,name',
                'order:id,order_number,created_at',
            ])
            ->latest();

        // Find delivered orders awaiting seller review
        $reviewedSellerOrderIds = SellerReview::query()
            ->where('user_id', $user->id)
            ->pluck('order_id');

        $ordersAwaitingSellerReview = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereNotIn('id', $reviewedSellerOrderIds)
            ->with([
                'items.product.seller:id,name',
            ])
            ->latest()
            ->get()
            ->filter(function ($order) {
                return $order->items->pluck('product.seller')->filter()->isNotEmpty();
            })
            ->values();

        // Counts
        $counts = [
            'reviewed' => $reviewsQuery->count(),
            'awaiting' => $awaitingQuery->count(),
            'seller_reviews' => $sellerReviewsQuery->count(),
            'awaiting_seller' => $ordersAwaitingSellerReview->count(),
        ];

        return Inertia::render('Buyer/Reviews', [
            'current_tab' => $tab,
            'counts' => $counts,
            'reviews' => $tab === 'reviewed' ? $reviewsQuery->paginate(10)->withQueryString() : null,
            'awaiting_items' => $tab === 'awaiting' ? $awaitingQuery->paginate(10)->withQueryString() : null,
            'seller_reviews' => $tab === 'seller_reviews' ? $sellerReviewsQuery->paginate(10)->withQueryString() : null,
            'orders_awaiting_seller_review' => $tab === 'seller_reviews' ? $ordersAwaitingSellerReview : [],
        ]);
    }

    /**
     * Store a product review with optional photo/video media attachments (BUYER-31).
     */
    public function store(
        Request $request,
        Product $product
    ): RedirectResponse {
        $user = $request->user();

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
            'media' => [
                'nullable',
                'array',
                'max:5',
            ],
            'media.*' => [
                'file',
                'mimes:jpeg,png,jpg,webp,mp4,mov',
                'max:10240', // 10MB
            ],
        ]);

        // Check buyer eligibility
        $eligible = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereHas('order', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->exists();

        if (!$eligible) {
            throw ValidationException::withMessages([
                'rating' => 'You can only review products that you have received and confirmed delivered.',
            ]);
        }

        // Prevent duplicate review
        if (Review::where('user_id', $user->id)->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'rating' => 'You have already reviewed this product. You can edit your existing review.',
            ]);
        }

        $mediaPaths = [];
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $mediaPaths[] = $file->store('reviews', 'public');
            }
        }

        Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'media' => $mediaPaths,
        ]);

        $this->recalculateProductRating($product);

        if ($product->seller_id && Schema::hasTable('seller_notifications')) {
            try {
                SellerNotification::create([
                    'user_id' => $product->seller_id,
                    'type' => 'review',
                    'title' => 'New Product Review',
                    'message' => "{$user->name} left a {$validated['rating']}-star review for \"{$product->name}\".",
                    'url' => route('seller.reviews'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification errors
            }
        }

        return back()->with(
            'status',
            'Your review has been submitted successfully.'
        );
    }

    /**
     * Edit / update an existing review (BUYER-29, BUYER-31).
     */
    public function update(
        Request $request,
        Review $review
    ): RedirectResponse {
        abort_unless((int) $review->user_id === (int) $request->user()->id, 404);

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
            'existing_media' => [
                'nullable',
                'array',
            ],
            'media' => [
                'nullable',
                'array',
                'max:5',
            ],
            'media.*' => [
                'file',
                'mimes:jpeg,png,jpg,webp,mp4,mov',
                'max:10240',
            ],
        ]);

        $mediaPaths = $validated['existing_media'] ?? [];

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $mediaPaths[] = $file->store('reviews', 'public');
            }
        }

        $review->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'media' => array_values($mediaPaths),
        ]);

        if ($review->product) {
            $this->recalculateProductRating($review->product);
        }

        return back()->with(
            'status',
            'Your review has been updated successfully.'
        );
    }

    /**
     * Delete a review.
     */
    public function destroy(Request $request, Review $review): RedirectResponse
    {
        abort_unless((int) $review->user_id === (int) $request->user()->id, 404);

        $product = $review->product;
        $review->delete();

        if ($product) {
            $this->recalculateProductRating($product);
        }

        return back()->with(
            'status',
            'Review removed successfully.'
        );
    }

    /**
     * Rate and review a seller after a completed order (BUYER-30).
     */
    public function storeSellerReview(
        Request $request,
        Order $order,
        User $seller
    ): RedirectResponse {
        $user = $request->user();

        abort_unless((int) $order->user_id === (int) $user->id, 404);

        if (!in_array(strtolower((string) $order->status), ['delivered', 'completed'], true)) {
            throw ValidationException::withMessages([
                'rating' => 'You can only rate sellers after your order has been delivered.',
            ]);
        }

        // Verify seller has products in this order
        $sellerHasItem = $order->items()
            ->whereHas('product', function ($query) use ($seller) {
                $query->where('seller_id', $seller->id);
            })
            ->exists();

        if (!$sellerHasItem) {
            throw ValidationException::withMessages([
                'rating' => 'This seller does not have any items in the specified order.',
            ]);
        }

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

        SellerReview::updateOrCreate(
            [
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'order_id' => $order->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        if (Schema::hasTable('seller_notifications')) {
            try {
                SellerNotification::create([
                    'user_id' => $seller->id,
                    'type' => 'review',
                    'title' => 'New Store Rating',
                    'message' => "{$user->name} rated your store {$validated['rating']} stars.",
                    'url' => route('seller.reviews'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification errors
            }
        }

        return back()->with(
            'status',
            "Thank you! Your rating for {$seller->name} has been recorded."
        );
    }

    /**
     * Recalculate average rating & count for a product.
     */
    private function recalculateProductRating(Product $product): void
    {
        $reviewQuery = Review::query()->where('product_id', $product->id);
        $reviewCount = $reviewQuery->count();
        $averageRating = $reviewCount > 0
            ? round((float) $reviewQuery->avg('rating'), 2)
            : 0;

        $product->update([
            'rating' => $averageRating,
            'reviews_count' => $reviewCount,
        ]);
    }
}
