<?php

namespace App\Models;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'seller_id',
        'name',
        'slug',
        'description',
        'price',
        'old_price',
        'stock',
        'low_stock_threshold',
        'image_path',
        'status',
        'is_featured',
        'rating',
        'reviews_count',
        'weight',
        'length',
        'width',
        'height',
        'brand',
        'condition',
        'variation_types',
    ];

    protected $appends = [
        'min_price',
        'max_price',
        'price_range',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'rating' => 'decimal:2',
            'is_featured' => 'boolean',
            'low_stock_threshold' => 'integer',
            'variation_types' => 'array',
        ];
    }

    public function getMinPriceAttribute(): string
    {
        if ($this->relationLoaded('variants')) {
            $prices = $this->variants
                ->pluck('price')
                ->filter(fn ($p) => $p !== null && (float) $p > 0)
                ->map(fn ($p) => (float) $p);

            if ($prices->isNotEmpty()) {
                return number_format($prices->min(), 2, '.', '');
            }
        }

        return number_format((float) ($this->attributes['price'] ?? 0), 2, '.', '');
    }

    public function getMaxPriceAttribute(): string
    {
        if ($this->relationLoaded('variants')) {
            $prices = $this->variants
                ->pluck('price')
                ->filter(fn ($p) => $p !== null && (float) $p > 0)
                ->map(fn ($p) => (float) $p);

            if ($prices->isNotEmpty()) {
                return number_format($prices->max(), 2, '.', '');
            }
        }

        return number_format((float) ($this->attributes['price'] ?? 0), 2, '.', '');
    }

    public function getPriceRangeAttribute(): string
    {
        $min = (float) $this->min_price;
        $max = (float) $this->max_price;

        $formatVal = fn (float $val) => floor($val) == $val
            ? '₱' . number_format($val, 0)
            : '₱' . number_format($val, 2);

        if (abs($min - $max) > 0.001) {
            return $formatVal($min) . ' – ' . $formatVal($max);
        }

        return '₱' . number_format($min, 2);
    }

    public function isLowStock(): bool
    {
        $threshold = $this->low_stock_threshold ?? 5;
        return (int) $this->stock <= (int) $threshold;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    /**
     * Compliance review history for this product.
     */
    public function complianceReviews(): HasMany
    {
        return $this->hasMany(ProductComplianceReview::class)
            ->latest('reviewed_at');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class)->latest();
    }

    public function storeCategories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            StoreCategory::class,
            'product_store_category',
            'product_id',
            'store_category_id'
        )->withTimestamps();
    }
}