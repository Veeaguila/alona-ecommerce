<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'order_item_id',
        'variant_id',
        'variant_name',
        'rating',
        'comment',
        'edit_count',
        'edited_at',
        'media',
        'seller_reply',
        'seller_replied_at',
        'is_reported',
        'report_reason',
        'report_notes',
        'report_status',
        'reported_at',
    ];

    protected $appends = [
        'can_edit',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'edit_count' => 'integer',
            'edited_at' => 'datetime',
            'media' => 'array',
            'seller_replied_at' => 'datetime',
            'is_reported' => 'boolean',
            'reported_at' => 'datetime',
        ];
    }

    public function getCanEditAttribute(): bool
    {
        $notEdited = ((int) ($this->edit_count ?? 0)) < 1;
        $within30Days = $this->created_at ? ! $this->created_at->copy()->addDays(30)->isPast() : true;

        return $notEdited && $within30Days;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
