<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'status',
        'decision',
        'rejection_reason',
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
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'evidence_images' => 'array',
            'return_shipping_history' => 'array',
            'restocked' => 'boolean',
            'buyer_shipped_at' => 'datetime',
            'item_received_at' => 'datetime',
            'seller_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
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
}
