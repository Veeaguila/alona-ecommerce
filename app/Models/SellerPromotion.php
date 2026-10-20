<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerPromotion extends Model
{
    protected $fillable = [
        'seller_id',
        'name',
        'type',
        'product_id',
        'product_ids',
        'min_quantity',
        'discount_type',
        'discount_value',
        'buy_product_id',
        'buy_quantity',
        'get_product_id',
        'get_quantity',
        'get_discount_percentage',
        'starts_at',
        'ends_at',
        'ended_at',
        'is_active',
    ];

    protected $appends = [
        'status_label',
    ];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'min_quantity' => 'integer',
            'discount_value' => 'decimal:2',
            'buy_quantity' => 'integer',
            'get_quantity' => 'integer',
            'get_discount_percentage' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function buyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'buy_product_id');
    }

    public function getProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'get_product_id');
    }

    public function isActiveNow(): bool
    {
        if (! $this->is_active || $this->ended_at !== null) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->greaterThan($now)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($now)) {
            return false;
        }

        return true;
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active || $this->ended_at !== null) {
            return 'Ended';
        }

        $now = now();

        if ($this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($now)) {
            return 'Ended';
        }

        if ($this->starts_at !== null && $this->starts_at->greaterThan($now)) {
            return 'Upcoming';
        }

        return 'Ongoing';
    }

    public function appliesToBundleProduct(int $productId): bool
    {
        if ($this->type !== 'bundle') {
            return false;
        }

        if ($this->product_id !== null && (int) $this->product_id === $productId) {
            return true;
        }

        $ids = array_map('intval', (array) ($this->product_ids ?? []));

        return in_array($productId, $ids, true);
    }
}

