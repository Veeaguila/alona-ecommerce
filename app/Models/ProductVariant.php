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
        'variant_attributes',
        'combination_key',
        'price',
        'stock',
        'weight',
        'is_active',
        'low_stock_alerted',
        'sku',
        'image_path',
    ];

    protected $appends = [
        'title',
        'regular_price',
        'old_price',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $variant): void {
            if ((int) ($variant->stock ?? 0) > 5) {
                $variant->low_stock_alerted = false;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('product_variants', 'combination_key')) {
                if (empty($variant->combination_key)) {
                    $variant->combination_key = $variant->generateCombinationKey();
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'variant_attributes' => 'array',
            'price' => 'decimal:2',
            'stock' => 'integer',
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
            'low_stock_alerted' => 'boolean',
        ];
    }

    public function getRegularPriceAttribute(): string
    {
        $price = (float) ($this->attributes['price'] ?? 0);
        if ($price <= 0) {
            $product = $this->relationLoaded('product')
                ? $this->product
                : ($this->product_id ? Product::find($this->product_id) : null);
            if ($product && (float) $product->getRawOriginal('price') > 0) {
                $price = (float) $product->getRawOriginal('price');
            }
        }

        return number_format($price, 2, '.', '');
    }

    public function getPriceAttribute($value): string
    {
        $regular = (float) ($value ?? $this->attributes['price'] ?? 0);
        $product = $this->relationLoaded('product')
            ? $this->product
            : ($this->product_id ? Product::find($this->product_id) : null);

        if ($regular <= 0 && $product && (float) $product->getRawOriginal('price') > 0) {
            $regular = (float) $product->getRawOriginal('price');
        }

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
        $attrs = $this->variant_attributes;
        if (is_array($attrs) && count($attrs) > 0) {
            $parts = [];
            foreach ($attrs as $key => $val) {
                if (filled($val)) {
                    $parts[] = (string) $val;
                }
            }
            if (count($parts) > 0) {
                return implode(' - ', $parts);
            }
        }

        return collect([$this->color, $this->size])
            ->filter(fn ($val) => $val !== null && $val !== '')
            ->implode('-');
    }

    public function generateCombinationKey(): string
    {
        $attrs = $this->variant_attributes;
        if (is_array($attrs) && count($attrs) > 0) {
            $pairs = [];
            foreach ($attrs as $k => $v) {
                if (filled($v)) {
                    $pairs[mb_strtolower(trim((string) $k))] = mb_strtolower(trim((string) $v));
                }
            }
            ksort($pairs);
            $parts = [];
            foreach ($pairs as $k => $v) {
                $parts[] = "{$k}:{$v}";
            }
            if (!empty($parts)) {
                return implode('|', $parts);
            }
        }

        $c = mb_strtolower(trim((string) ($this->color ?? '')));
        $s = mb_strtolower(trim((string) ($this->size ?? '')));
        return "color:{$c}|size:{$s}";
    }

    public function getTitleAttribute(): string
    {
        return $this->label();
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