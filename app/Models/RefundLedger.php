<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_reference',
        'order_return_request_id',
        'order_id',
        'order_item_id',
        'seller_id',
        'buyer_id',
        'original_amount',
        'refunded_amount',
        'refund_type',
        'refund_method',
        'status',
        'reason',
        'notes',
        'transaction_reference',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(OrderReturnRequest::class, 'order_return_request_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

