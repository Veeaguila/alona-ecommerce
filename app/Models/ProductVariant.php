<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'color',
        'size',
        'price',
        'stock',
        'low_stock_alerted',
        'sku',
        'image_path',
    ];

    protected $appends = [
        'regular_price',
        'old_price',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $variant): void {
            if ((int) ($variant->stock ?? 0) > 5) {
                $variant->low_stock_alerted = false;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_alerted' => 'boolean',
        ];
    }

    public function getRegularPriceAttribute(): string
    {
        return number_format((float) ($this->attributes['price'] ?? 0), 2, '.', '');
    }

    public function getPriceAttribute($value): string
    {
        $regular = (float) ($value ?? $this->attributes['price'] ?? 0);
        $product = $this->relationLoaded('product')
            ? $this->product
            : ($this->product_id ? Product::find($this->product_id) : null);

        $sale = $product?->activeSale();
        if ($sale && $regular > 0) {
            return number_format($sale->discountedPriceFor($regular), 2, '.', '');
        }

        return number_format($regular, 2, '.', '');
    }

    public function getOldPriceAttribute(): ?string
    {
        $regular = (float) ($this->attributes['price'] ?? 0);
        if ($regular <= 0) {
            return null;
        }

        $product = $this->relationLoaded('product')
            ? $this->product
            : ($this->product_id ? Product::find($this->product_id) : null);

        $sale = $product?->activeSale();
        if ($sale) {
            return number_format($regular, 2, '.', '');
        }

        return null;
    }

    public function label(): string
    {
        return collect([$this->color, $this->size])
            ->filter(fn ($val) => $val !== null && $val !== '')
            ->implode('-');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class)->latest();
    }
}