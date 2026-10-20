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