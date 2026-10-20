<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSale extends Model
{
    protected $fillable = [
        'seller_id',
        'product_id',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'ended_at',
    ];

    protected $appends = [
        'status_label',
        'sale_price',
        'discount_badge',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
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

    public function isActiveNow(): bool
    {
        if ($this->ended_at !== null) {
            return false;
        }

        $now = now();

        return $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->lessThanOrEqualTo($now)
            && $this->ends_at->greaterThan($now);
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->ended_at !== null) {
            return 'Ended';
        }

        $now = now();

        if ($this->ends_at && $this->ends_at->lessThanOrEqualTo($now)) {
            return 'Ended';
        }

        if ($this->starts_at && $this->starts_at->greaterThan($now)) {
            return 'Upcoming';
        }

        return 'Ongoing';
    }

    public function discountedPriceFor(float $regularPrice): float
    {
        if ($regularPrice <= 0) {
            return 0.0;
        }

        if ($this->type === 'percentage') {
            $pct = (float) $this->value;
            return round($regularPrice * (100.0 - $pct) / 100.0, 2);
        }

        // Fixed sale price
        return round(min($regularPrice, (float) $this->value), 2);
    }

    public function getSalePriceAttribute(): string
    {
        $product = $this->relationLoaded('product')
            ? $this->product
            : ($this->product_id ? Product::find($this->product_id) : null);

        $regularPrice = $product ? (float) $product->getRawOriginal('price') : 0.0;

        return number_format($this->discountedPriceFor($regularPrice), 2, '.', '');
    }

    public function getDiscountBadgeAttribute(): string
    {
        if ($this->type === 'percentage') {
            $pct = (int) round((float) $this->value);
            return "−{$pct}%";
        }

        $product = $this->relationLoaded('product')
            ? $this->product
            : ($this->product_id ? Product::find($this->product_id) : null);

        $regularPrice = $product ? (float) $product->getRawOriginal('price') : 0.0;
        if ($regularPrice > 0) {
            $pct = (int) round((1.0 - ((float) $this->value / $regularPrice)) * 100.0);
            if ($pct > 0) {
                return "−{$pct}%";
            }
        }

        return 'SALE';
    }
}

