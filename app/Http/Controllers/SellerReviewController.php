<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\Complaint;
use App\Models\Review;
use App\Models\SellerReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerReviewController extends Controller
{
    /**
     * Display seller feedback, rating breakdown, and reviews (SELLER-32, 33, 34, 35).
     */
    public function index(Request $request): Response
    {
        $seller = $request->user();
        $sellerId = $seller->id;

        $tab = $request->input('tab', 'product'); // 'product' | 'store' | 'all'
        $selectedRating = $request->input('rating');
        $hasMediaOnly = $request->boolean('has_media');
        $isReportedOnly = $request->boolean('reported');
        $replyStatus = $request->input('reply_status', 'all'); // 'all' | 'replied' | 'unreplied'
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'newest'); // 'newest' | 'oldest' | 'highest' | 'lowest'

        // 1. COMPUTE REAL PRODUCT REVIEWS BREAKDOWN (SELLER-35)
        $productReviewsBase = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId));

        $totalProductReviews = (int) $productReviewsBase->count();
        $productRatingAvg = $totalProductReviews > 0
            ? round((float) $productReviewsBase->avg('rating'), 2)
            : 0.0;

        $productDistribution = $this->calculateRatingBreakdown($productReviewsBase);

        // 2. COMPUTE REAL STORE REVIEWS BREAKDOWN (SELLER-33, SELLER-35)
        $storeReviewsBase = SellerReview::query()
            ->where('seller_id', $sellerId);

        $totalStoreReviews = (int) $storeReviewsBase->count();
        $storeRatingAvg = $totalStoreReviews > 0
            ? round((float) $storeReviewsBase->avg('rating'), 2)
            : 0.0;

        $storeDistribution = $this->calculateRatingBreakdown($storeReviewsBase);

        // 3. COMBINED AGGREGATE METRICS
        $totalCombinedCount = $totalProductReviews + $totalStoreReviews;
        $combinedRatingAvg = $totalCombinedCount > 0
            ? round((($productRatingAvg * $totalProductReviews) + ($storeRatingAvg * $totalStoreReviews)) / $totalCombinedCount, 2)
            : 0.0;

        $combinedDistribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $starCount = ($productDistribution[$star]['count'] ?? 0) + ($storeDistribution[$star]['count'] ?? 0);
            $combinedDistribution[$star] = [
                'stars' => $star,
                'count' => $starCount,
                'percentage' => $totalCombinedCount > 0 ? round(($starCount / $totalCombinedCount) * 100, 1) : 0,
            ];
        }

        // 4. QUERY PAGINATED REVIEWS ACCORDING TO TAB & FILTERS
        $reviews = null;
        $storeReviews = null;

        if ($tab === 'store') {
            $query = SellerReview::query()
                ->where('seller_id', $sellerId)
                ->with([
                    'user:id,name,email,avatar',
                    'order:id,order_number,created_at,status',
                ]);

            if ($selectedRating) {
                $query->where('rating', (int) $selectedRating);
            }

            if ($hasMediaOnly) {
                $query->whereNotNull('media')->where('media', '!=', '[]')->where('media', '!=', '');
            }

            if ($isReportedOnly) {
                $query->where('is_reported', true);
            }

            if ($replyStatus === 'replied') {
                $query->whereNotNull('seller_reply');
            } elseif ($replyStatus === 'unreplied') {
                $query->whereNull('seller_reply');
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"));
                });
            }

            $this->applySorting($query, $sort);

            $storeReviews = $query->paginate(10)->withQueryString();
        } else {
            // Default: Product reviews
            $query = Review::query()
                ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
                ->with([
                    'user:id,name,email,avatar',
                    'product:id,name,image_path,price,slug',
                    'variant:id,product_id,color,size',
                ]);

            if ($selectedRating) {
                $query->where('rating', (int) $selectedRating);
            }

            if ($hasMediaOnly) {
                $query->whereNotNull('media')->where('media', '!=', '[]')->where('media', '!=', '');
            }

            if ($isReportedOnly) {
                $query->where('is_reported', true);
            }

            if ($replyStatus === 'replied') {
                $query->whereNotNull('seller_reply');
            } elseif ($replyStatus === 'unreplied') {
                $query->whereNull('seller_reply');
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
                });
            }

            $this->applySorting($query, $sort);

            $reviews = $query->paginate(10)->withQueryString();
        }

        return Inertia::render('Seller/Reviews', [
            'tab' => $tab,
            'reviews' => $reviews,
            'storeReviews' => $storeReviews,
            'stats' => [
                'total_product_reviews' => $totalProductReviews,
                'product_rating_avg' => $productRatingAvg,
                'product_distribution' => $productDistribution,
                'total_store_reviews' => $totalStoreReviews,
                'store_rating_avg' => $storeRatingAvg,
                'store_distribution' => $storeDistribution,
                'total_combined' => $totalCombinedCount,
                'combined_rating_avg' => $combinedRatingAvg,
                'combined_distribution' => $combinedDistribution,
            ],
            'filters' => [
                'tab' => $tab,
                'rating' => $selectedRating,
                'has_media' => $hasMediaOnly,
                'reported' => $isReportedOnly,
                'reply_status' => $replyStatus,
                'search' => $search,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Post a reply to a product review (SEL-REV-002, SEL-REV-003 / A12).
     */
    public function reply(Request $request, Review $review): RedirectResponse
    {
        $review->loadMissing('product');
        abort_unless($review->product && (int) $review->product->seller_id === (int) $request->user()->id, 403);

        // SEL-REV-003: Check if already replied and trying to post a second reply without edit flag/method
        $isEdit = $request->boolean('is_edit') || $request->isMethod('put') || $request->isMethod('patch');
        if ($review->seller_reply !== null && ! $isEdit) {
            throw ValidationException::withMessages([
                'seller_reply' => 'A second reply is not possible. You may edit your existing reply instead.',
            ]);
        }

        $data = $request->validate([
            'seller_reply' => ['required', 'string', 'max:1500'],
        ]);

        $review->update([
            'seller_reply' => $data['seller_reply'],
            'seller_replied_at' => now(),
        ]);

        if (Schema::hasTable('buyer_notifications') && $review->user_id) {
            try {
                BuyerNotification::create([
                    'user_id' => $review->user_id,
                    'title' => 'Seller Replied to Your Review',
                    'type' => 'review',
                    'message' => "{$request->user()->name} replied to your review on \"{$review->product->name}\".",
                    'action_url' => route('buyer.reviews'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification failure
            }
        }

        $message = $isEdit ? 'Your response has been updated.' : 'Your response has been posted.';

        return back()->with('status', $message);
    }

    /**
     * Post a reply to a store rating (SELLER-33, SEL-REV-003).
     */
    public function replyStoreReview(Request $request, SellerReview $sellerReview): RedirectResponse
    {
        abort_unless((int) $sellerReview->seller_id === (int) $request->user()->id, 403);

        $isEdit = $request->boolean('is_edit') || $request->isMethod('put') || $request->isMethod('patch');
        if ($sellerReview->seller_reply !== null && ! $isEdit) {
            throw ValidationException::withMessages([
                'seller_reply' => 'A second reply is not possible. You may edit your existing reply instead.',
            ]);
        }

        $data = $request->validate([
            'seller_reply' => ['required', 'string', 'max:1500'],
        ]);

        $sellerReview->update([
            'seller_reply' => $data['seller_reply'],
            'seller_replied_at' => now(),
        ]);

        if (Schema::hasTable('buyer_notifications') && $sellerReview->user_id) {
            try {
                BuyerNotification::create([
                    'user_id' => $sellerReview->user_id,
                    'title' => 'Seller Replied to Your Store Rating',
                    'type' => 'review',
                    'message' => "{$request->user()->name} replied to your store rating feedback.",
                    'action_url' => route('buyer.reviews'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification failure
            }
        }

        $message = $isEdit ? 'Your response has been updated.' : 'Your response to the store rating has been posted.';

        return back()->with('status', $message);
    }

    /**
     * Direct delete request is blocked (SEL-REV-004, Assumption A12).
     * Sellers are not permitted to delete or hide customer reviews.
     */
    public function destroy(Request $request, Review $review): RedirectResponse
    {
        abort(403, 'Sellers are not permitted to delete or hide customer reviews.');
    }

    /**
     * Report an inappropriate or fraudulent product review to platform admins (SELLER-32).
     */
    public function report(Request $request, Review $review): RedirectResponse
    {
        $review->loadMissing(['product', 'user']);
        abort_unless($review->product && (int) $review->product->seller_id === (int) $request->user()->id, 403);

        if ($review->is_reported) {
            return back()->withErrors(['report' => 'This review has already been reported to platform administrators.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:abusive_language,fake_or_fraudulent,competitor_sabotage,extortion_or_blackmail,irrelevant_or_spam,wrong_product,other'],
            'notes' => ['required', 'string', 'min:5', 'max:1500'],
        ]);

        $seller = $request->user();

        // 1. Update review status
        $review->update([
            'is_reported' => true,
            'report_reason' => $validated['reason'],
            'report_notes' => $validated['notes'],
            'report_status' => 'pending',
            'reported_at' => now(),
        ]);

        // 2. File platform admin dispute / complaint ticket
        $reasonLabels = [
            'abusive_language' => 'Abusive or Inappropriate Language',
            'fake_or_fraudulent' => 'Fake or Fraudulent Review',
            'competitor_sabotage' => 'Competitor Malicious Review',
            'extortion_or_blackmail' => 'Review Extortion or Blackmail',
            'irrelevant_or_spam' => 'Spam or Irrelevant Content',
            'wrong_product' => 'Mentions Completely Different Product',
            'other' => 'Other Policy Violation',
        ];

        $reasonText = $reasonLabels[$validated['reason']] ?? ucfirst(str_replace('_', ' ', $validated['reason']));

        $sellerStoreName = $seller->store_name ?? $seller->name;

        Complaint::create([
            'seller_id' => $seller->id,
            'buyer_id' => $review->user_id,
            'product_id' => $review->product_id,
            'type' => 'review_report',
            'subject' => "Reported Inappropriate Review #{$review->id} ({$reasonText})",
            'description' => "Seller [{$sellerStoreName}] has reported a {$review->rating}-star review on product \"{$review->product->name}\".\n\nBuyer Review Comment: \"{$review->comment}\"\n\nReport Category: {$reasonText}\nSeller Explanation: {$validated['notes']}",
            'status' => 'pending',
            'contact_email' => $seller->email,
        ]);

        return back()->with('status', 'Review has been reported to platform administrators for investigation.');
    }

    /**
     * Report an inappropriate or fraudulent store rating to platform admins (SELLER-32, SELLER-33).
     */
    public function reportStoreReview(Request $request, SellerReview $sellerReview): RedirectResponse
    {
        $sellerReview->loadMissing(['order', 'user']);
        abort_unless((int) $sellerReview->seller_id === (int) $request->user()->id, 403);

        if ($sellerReview->is_reported) {
            return back()->withErrors(['report' => 'This store rating has already been reported to platform administrators.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:abusive_language,fake_or_fraudulent,competitor_sabotage,extortion_or_blackmail,irrelevant_or_spam,wrong_product,other'],
            'notes' => ['required', 'string', 'min:5', 'max:1500'],
        ]);

        $seller = $request->user();

        // 1. Update seller review status
        $sellerReview->update([
            'is_reported' => true,
            'report_reason' => $validated['reason'],
            'report_notes' => $validated['notes'],
            'report_status' => 'pending',
            'reported_at' => now(),
        ]);

        // 2. File platform admin dispute / complaint ticket
        $reasonLabels = [
            'abusive_language' => 'Abusive or Inappropriate Language',
            'fake_or_fraudulent' => 'Fake or Fraudulent Rating',
            'competitor_sabotage' => 'Competitor Malicious Rating',
            'extortion_or_blackmail' => 'Rating Extortion or Blackmail',
            'irrelevant_or_spam' => 'Spam or Irrelevant Feedback',
            'wrong_product' => 'Order Mismatch',
            'other' => 'Other Policy Violation',
        ];

        $reasonText = $reasonLabels[$validated['reason']] ?? ucfirst(str_replace('_', ' ', $validated['reason']));

        $sellerStoreName = $seller->store_name ?? $seller->name;
        $orderNumber = $sellerReview->order?->order_number ?? 'N/A';

        Complaint::create([
            'seller_id' => $seller->id,
            'buyer_id' => $sellerReview->user_id,
            'order_id' => $sellerReview->order_id,
            'type' => 'review_report',
            'subject' => "Reported Inappropriate Store Rating #{$sellerReview->id} ({$reasonText})",
            'description' => "Seller [{$sellerStoreName}] has reported a {$sellerReview->rating}-star store rating for Order #{$orderNumber}.\n\nCustomer Feedback: \"{$sellerReview->comment}\"\n\nReport Category: {$reasonText}\nSeller Explanation: {$validated['notes']}",
            'status' => 'pending',
            'contact_email' => $seller->email,
        ]);

        return back()->with('status', 'Store rating has been reported to platform administrators for investigation.');
    }

    /**
     * Calculate star distribution and percentages dynamically from the database.
     */
    private function calculateRatingBreakdown($query): array
    {
        $cloned = clone $query;
        $total = (int) $cloned->count();

        // Group counts by rating
        $counts = (clone $query)
            ->select('rating', DB::raw('count(*) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $starCount = (int) ($counts[$star] ?? 0);
            $breakdown[$star] = [
                'stars' => $star,
                'count' => $starCount,
                'percentage' => $total > 0 ? round(($starCount / $total) * 100, 1) : 0.0,
            ];
        }

        return $breakdown;
    }

    /**
     * Apply sorting logic.
     */
    private function applySorting($query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest(),
            'highest' => $query->orderByDesc('rating')->latest(),
            'lowest' => $query->orderBy('rating')->latest(),
            default => $query->latest(),
        };
    }
}
