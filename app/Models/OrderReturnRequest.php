<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class OrderReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'buyer_id',
        'seller_id',
        'type',
        'reason',
        'reason_details',
        'requested_amount',
        'evidence_images',
        'response_deadline',
        'status',
        'decision',
        'rejection_reason',
        'dispute_reason',
        'return_instructions',
        'return_warehouse_address',
        'return_carrier',
        'return_tracking_number',
        'return_waybill_url',
        'return_shipping_status',
        'return_shipping_history',
        'buyer_shipped_at',
        'item_received_at',
        'inspection_status',
        'inspection_notes',
        'refunded_amount',
        'refund_method',
        'restocked',
        'seller_response',
        'admin_response',
        'seller_responded_at',
        'resolved_at',
        'escalated_at',
        'auto_approved_at',
    ];

    protected $appends = [
        'response_deadline_label',
        'status_label',
        'can_escalate',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'evidence_images' => 'array',
            'return_shipping_history' => 'array',
            'restocked' => 'boolean',
            'response_deadline' => 'datetime',
            'buyer_shipped_at' => 'datetime',
            'item_received_at' => 'datetime',
            'seller_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'auto_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrderReturnRequest $request) {
            if (! $request->response_deadline) {
                $baseTime = $request->created_at ? Carbon::parse($request->created_at) : now();
                $request->response_deadline = $baseTime->copy()->addDays(3);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function refundLedger(): HasOne
    {
        return $this->hasOne(RefundLedger::class, 'order_return_request_id');
    }

    public function getResponseDeadlineAttribute($value): ?Carbon
    {
        if ($value) {
            return Carbon::parse($value);
        }

        if ($this->created_at) {
            return Carbon::parse($this->created_at)->addDays(3);
        }

        return null;
    }

    public function getResponseDeadlineLabelAttribute(): string
    {
        $deadline = $this->response_deadline;
        if (! $deadline) {
            return '3 days';
        }

        return '3 days (' . $deadline->format('M d, Y h:i A') . ')';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Review',
            'approved' => 'Return Approved',
            'rejected' => 'Rejected',
            'in_dispute' => 'In Dispute',
            'item_returned' => 'Item Returned',
            'refunded' => 'Refunded',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getCanEscalateAttribute(): bool
    {
        if ($this->status !== 'rejected' || $this->escalated_at !== null) {
            return false;
        }

        $rejectedAt = $this->seller_responded_at ?? $this->resolved_at ?? $this->updated_at;
        if (! $rejectedAt) {
            return true;
        }

        return Carbon::parse($rejectedAt)->addDays(3)->isFuture();
    }

    public function addShippingMilestone(string $status, string $title, ?string $location = null, ?string $description = null): void
    {
        $history = $this->return_shipping_history ?? [];
        $history[] = [
            'status' => $status,
            'title' => $title,
            'location' => $location ?? 'Sorting / Logistics Hub',
            'description' => $description ?? '',
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update([
            'return_shipping_status' => $status,
            'return_shipping_history' => $history,
        ]);
    }

    /**
     * Auto-approve pending return requests that have exceeded the 3-day window (SEL-RET-006 / A31).
     */
    public static function autoApproveExpiredPendingRequests(): int
    {
        $now = now();
        $expiredRequests = static::query()
            ->where('status', 'pending')
            ->where(function ($q) use ($now) {
                $q->where('created_at', '<=', $now->copy()->subDays(3))
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNotNull('response_deadline')
                         ->where('response_deadline', '<=', $now);
                  });
            })
            ->with(['order', 'seller', 'buyer'])
            ->get();

        $count = 0;
        foreach ($expiredRequests as $returnRequest) {
            $seller = $returnRequest->seller;
            $sellerAddress = $seller
                ? trim(implode(', ', array_filter([
                    $seller->street_address ?? $seller->address ?? null,
                    $seller->barangay,
                    $seller->municipality,
                    $seller->province,
                ])))
                : 'Seller Store Warehouse';

            $orderNumber = $returnRequest->order?->order_number ?? (string) $returnRequest->order_id;

            $returnRequest->update([
                'status' => 'approved',
                'decision' => 'approved',
                'auto_approved_at' => $now,
                'seller_responded_at' => $now,
                'seller_response' => 'Auto-approved after 3 days without seller response.',
                'return_instructions' => 'Auto-approved: Please pack the item securely and ship it back to the seller warehouse.',
                'return_warehouse_address' => $sellerAddress ?: 'Seller Warehouse',
            ]);

            // Add shipping milestone
            $returnRequest->addShippingMilestone(
                'approved',
                'Return Auto-Approved',
                $sellerAddress ?: 'Seller Warehouse',
                'Return automatically authorized as response deadline elapsed without seller response.'
            );

            // Notify buyer
            if (Schema::hasTable('buyer_notifications')) {
                BuyerNotification::create([
                    'user_id' => $returnRequest->buyer_id,
                    'title' => 'Return Request Auto-Approved',
                    'message' => "Your return request for Order #{$orderNumber} was automatically approved because the seller did not respond within 3 days. Return instructions are now available in your order details.",
                ]);
            }

            // Notify seller
            if (Schema::hasTable('seller_notifications')) {
                SellerNotification::create([
                    'user_id' => $returnRequest->seller_id,
                    'title' => 'Return Request Auto-Approved',
                    'message' => "Return request for Order #{$orderNumber} was automatically approved due to no response within the 3-day deadline.",
                ]);
            }

            $count++;
        }

        return $count;
    }
}
