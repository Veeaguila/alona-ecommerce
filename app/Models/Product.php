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
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'rating' => 'decimal:2',
            'is_featured' => 'boolean',
            'low_stock_threshold' => 'integer',
        ];
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