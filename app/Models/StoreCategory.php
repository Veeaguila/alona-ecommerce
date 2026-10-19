<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'parent_id',
        'name',
        'slug',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(StoreCategory::class, 'parent_id')
            ->orderBy('sort_order');
    }

    public function subcategories(): HasMany
    {
        return $this->children();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_store_category',
            'store_category_id',
            'product_id'
        )->withTimestamps();
    }

    /**
     * Determine nesting depth level (1 = Root, 2 = Subcategory).
     */
    public function getDepth(): int
    {
        if (! $this->parent_id) {
            return 1;
        }

        return 1 + ($this->parent?->getDepth() ?? 1);
    }
}

