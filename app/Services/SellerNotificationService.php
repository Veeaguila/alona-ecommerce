<?php

namespace App\Services;

use App\Models\OrderReturnRequest;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerNotification;
use App\Models\SellerPayoutRequest;
use App\Models\SellerReview;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class SellerNotificationService
{
    /**
     * SELLER-58: Send automated low-stock threshold alert to seller.
     */
    public static function sendLowStockAlert(Product $product): ?SellerNotification
    {
        if (!$product->seller_id || !Schema::hasTable('seller_notifications')) {
            return null;
        }

        $threshold = $product->low_stock_threshold ?? 5;
        $currentStock = (int) $product->stock;

        // Check if stock is at or below threshold
        if ($currentStock > $threshold) {
            return null;
        }

        // Prevent duplicate alerts sent within the last 12 hours for this product
        $recentAlert = SellerNotification::where('user_id', $product->seller_id)
            ->where('type', 'low_stock')
            ->where('message', 'like', "%{$product->name}%")
            ->where('created_at', '>=', Carbon::now()->subHours(12))
            ->first();

        if ($recentAlert) {
            return null;
        }

        $urgency = $currentStock === 0 ? 'OUT OF STOCK' : 'LOW STOCK';

        return SellerNotification::create([
            'user_id' => $product->seller_id,
            'type' => 'low_stock',
            'title' => "[{$urgency}] {$product->name}",
            'message' => $currentStock === 0
                ? "{$product->name} is completely out of stock (0 items remaining). Threshold is {$threshold} units. Please restock immediately to avoid missed sales."
                : "{$product->name} is running low on stock with only {$currentStock} item(s) remaining (Threshold: {$threshold} units). Please replenish your inventory.",
            'url' => route('seller.stock-monitoring'),
        ]);
    }

    /**
     * SELLER-59: Send automated notification for new product or store review.
     */
    public static function sendProductReviewAlert(Review $review): ?SellerNotification
    {
        $product = $review->product;
        if (!$product || !$product->seller_id || !Schema::hasTable('seller_notifications')) {
            return null;
        }

        $buyerName = $review->user?->name ?? 'A customer';
        $commentSnippet = $review->comment ? " - \"{$review->comment}\"" : '';

        return SellerNotification::create([
            'user_id' => $product->seller_id,
            'type' => 'review',
            'title' => 'New Product Review',
            'message' => "{$buyerName} left a {$review->rating}-star review for \"{$product->name}\"{$commentSnippet}.",
            'url' => route('seller.reviews'),
        ]);
    }

    /**
     * SELLER-59: Send automated notification for new store rating.
     */
    public static function sendStoreReviewAlert(SellerReview $sellerReview): ?SellerNotification
    {
        if (!$sellerReview->seller_id || !Schema::hasTable('seller_notifications')) {
            return null;
        }

        $buyerName = $sellerReview->user?->name ?? 'A customer';
        $commentSnippet = $sellerReview->comment ? " - \"{$sellerReview->comment}\"" : '';

        return SellerNotification::create([
            'user_id' => $sellerReview->seller_id,
            'type' => 'review',
            'title' => 'New Store Rating',
            'message' => "{$buyerName} rated your store {$sellerReview->rating} stars{$commentSnippet}.",
            'url' => route('seller.reviews'),
        ]);
    }

    /**
     * SELLER-60: Send immediate notification when buyer submits a return or refund request.
     */
    public static function sendReturnRequestAlert(OrderReturnRequest $returnRequest): ?SellerNotification
    {
        if (!$returnRequest->seller_id || !Schema::hasTable('seller_notifications')) {
            return null;
        }

        $productName = $returnRequest->orderItem?->product_name
            ?: ($returnRequest->orderItem?->product?->name ?? 'Item');

        $buyerName = $returnRequest->buyer?->name ?? 'Buyer';
        $orderNumber = $returnRequest->order?->order_number ?: ('ORD-' . $returnRequest->order_id);
        $typeLabel = $returnRequest->type === 'refund_only' ? 'Refund' : 'Return & Refund';

        return SellerNotification::create([
            'user_id' => $returnRequest->seller_id,
            'type' => 'return_request',
            'title' => "New {$typeLabel} Request",
            'message' => "{$buyerName} requested a {$typeLabel} for \"{$productName}\" on Order #{$orderNumber}. Reason: {$returnRequest->reason}",
            'url' => route('seller.returns'),
        ]);
    }

    /**
     * SELLER-61: Send notification when payout request status changes (approved, processed, rejected).
     */
    public static function sendPayoutStatusAlert(SellerPayoutRequest $payout, string $status, ?string $reason = null): ?SellerNotification
    {
        if (!$payout->seller_id || !Schema::hasTable('seller_notifications')) {
            return null;
        }

        $formattedAmount = '₱' . number_format((float) $payout->amount, 2);
        $methodName = strtoupper(str_replace('_', ' ', $payout->payout_method));

        $title = match ($status) {
            'processing' => 'Payout Under Processing',
            'approved', 'completed' => 'Payout Approved & Disbursed',
            'rejected' => 'Payout Request Declined',
            default => 'Payout Status Updated: ' . ucfirst($status),
        };

        $message = match ($status) {
            'processing' => "Your withdrawal request for {$formattedAmount} via {$methodName} ({$payout->payout_reference}) is currently being processed by administration.",
            'approved', 'completed' => "Your payout request for {$formattedAmount} ({$payout->payout_reference}) has been approved and successfully disbursed.",
            'rejected' => "Your payout request for {$formattedAmount} ({$payout->payout_reference}) was declined." . ($reason ? " Reason: {$reason}" : ""),
            default => "Your payout request ({$payout->payout_reference}) has been marked as {$status}.",
        };

        return SellerNotification::create([
            'user_id' => $payout->seller_id,
            'type' => 'payout',
            'title' => $title,
            'message' => $message,
            'url' => route('seller.notifications'),
        ]);
    }
}

