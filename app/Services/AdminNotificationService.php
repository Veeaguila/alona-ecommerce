<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AdminNotification;
use App\Models\Product;
use Illuminate\Support\Carbon;

class AdminNotificationService
{
    /**
     * Notify administrators that a product submission requires manual review.
     * Prevents duplicate unread notifications for the same product submission.
     */
    public function notifyProductReviewRequired(Product $product, array $verificationResult): AdminNotification
    {
        $seller = $product->seller;
        $sellerName = $seller?->store_name ?: ($seller?->name ?: "Seller #{$product->seller_id}");
        $submittedAt = $product->created_at ? $product->created_at->format('M d, Y h:i A') : now()->format('M d, Y h:i A');

        $status = $verificationResult['status'] ?? 'pending';
        $issueType = $verificationResult['issue_type'] ?? 'general';
        $explanation = $verificationResult['explanation'] ?? 'Automatic approval could not be completed. Please review the product details.';
        $recommendedAction = $verificationResult['recommended_action'] ?? 'Please inspect the product listing, images, and description, then approve or reject accordingly.';
        $confidence = isset($verificationResult['confidence']) ? round((float) $verificationResult['confidence'] * 100, 1) : null;

        $title = "Manual Review Required: {$product->name} (ID: #{$product->id})";

        $message = "Product '{$product->name}' (ID: #{$product->id}) submitted by {$sellerName} on {$submittedAt} requires manual review. "
            . "Reason: {$explanation} "
            . "Recommended action: {$recommendedAction}";

        $reviewUrl = route('admin.compliance', [
            'search' => $product->name,
            'status' => 'pending',
        ]);

        $notificationData = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'seller_id' => $product->seller_id,
            'seller_name' => $sellerName,
            'submitted_at' => $submittedAt,
            'ai_verification_status' => $status,
            'specific_issue' => $issueType,
            'confidence_score' => $confidence,
            'explanation' => $explanation,
            'recommended_action' => $recommendedAction,
            'review_url' => $reviewUrl,
        ];

        // Check if an existing unread notification exists for this product to prevent duplicate alerts
        $existing = AdminNotification::where('product_id', $product->id)
            ->whereNull('read_at')
            ->first();

        if ($existing) {
            $existing->update([
                'title' => $title,
                'message' => $message,
                'data' => $notificationData,
                'action_url' => $reviewUrl,
                'updated_at' => now(),
            ]);

            return $existing;
        }

        $notification = AdminNotification::create([
            'admin_id' => null, // Visible to all platform administrators
            'product_id' => $product->id,
            'type' => 'ai_product_review_needed',
            'title' => $title,
            'message' => $message,
            'data' => $notificationData,
            'action_url' => $reviewUrl,
        ]);

        // Audit Trail entry
        AdminAuditLog::record(
            auth()->id() ?? 1,
            'product_ai_verification_failed',
            "AI verification failed for product '{$product->name}' (ID: #{$product->id}). Status: {$status}. {$explanation}",
            $product,
            $notificationData
        );

        return $notification;
    }

    /**
     * Mark all notifications for a specific product as resolved/read.
     */
    public function resolveProductNotifications(Product $product): int
    {
        return AdminNotification::where('product_id', $product->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}

