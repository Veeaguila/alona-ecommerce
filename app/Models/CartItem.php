<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'product_variant_id',
        'variant_label',
        'quantity',
    ];

    protected $appends = [
        'is_unavailable',
        'unavailable_reason',
        'stock_warning',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->product_variant_id && empty($item->variant_label)) {
                $variant = ProductVariant::find($item->product_variant_id);
                if ($variant) {
                    $item->variant_label = $variant->label();
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function getUnavailableReasonAttribute(): ?string
    {
        if ($this->relationLoaded('product') && $this->product && $this->product->status !== 'approved') {
            return 'Product unavailable';
        }

        if ($this->product_variant_id === null) {
            if (! empty($this->variant_label)) {
                return 'Variation unavailable';
            }
            if ($this->relationLoaded('product') && $this->product && $this->product->relationLoaded('variants') && $this->product->variants->isNotEmpty()) {
                return 'Variation unavailable';
            }
        } elseif ($this->relationLoaded('variant') && $this->variant === null) {
            return 'Variation unavailable';
        }

        return null;
    }

    public function getIsUnavailableAttribute(): bool
    {
        return $this->unavailable_reason !== null;
    }

    public function getStockWarningAttribute(): ?string
    {
        if ($this->is_unavailable) {
            return null;
        }

        $stock = null;
        if ($this->product_variant_id) {
            $variant = $this->relationLoaded('variant') ? $this->variant : $this->variant()->first();
            if ($variant) {
                $stock = (int) $variant->stock;
            }
        } elseif ($this->product_id) {
            $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();
            if ($product) {
                $stock = (int) $product->stock;
            }
        }

        if ($stock === null) {
            return null;
        }

        if ($stock <= 0) {
            return 'Sold out';
        }

        if ((int) $this->quantity > $stock) {
            return "Only {$stock} left";
        }

        return null;
    }

    /**
     * Buyer who owns this cart item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    /**
     * Product belonging to this cart item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    /**
     * Optional product variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }
}
