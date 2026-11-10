<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('name');
    }

    public function subcategories(): HasMany
    {
        return $this->children();
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    public function activeSubcategories(): HasMany
    {
        return $this->activeChildren();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function variantTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(VariantType::class, 'category_variant_types')
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps()
            ->orderBy('category_variant_types.sort_order')
            ->orderBy('variant_types.name');
    }

    public function activeVariantTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->variantTypes()->where('variant_types.is_active', true);
    }

    /**
     * Get effective variant types (inheriting from parent if subcategory has none mapped).
     */
    public function getEffectiveVariantTypes()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('category_variant_types') || ! \Illuminate\Support\Facades\Schema::hasTable('variant_types')) {
            return collect();
        }

        $types = $this->activeVariantTypes()->with('activeValues')->get();

        if ($types->isEmpty() && $this->parent_id) {
            $parent = $this->parent;
            if ($parent) {
                return $parent->activeVariantTypes()->with('activeValues')->get();
            }
        }

        return $types;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOnlySubcategories($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function isSubcategory(): bool
    {
        return ! is_null($this->parent_id);
    }

    /**
     * Get all descendant category IDs including self.
     *
     * @return array<int>
     */
    public function getDescendantIds(): array
    {
        $ids = [$this->id];
        $childIds = $this->children()->pluck('id')->all();

        return array_values(array_unique(array_merge($ids, $childIds)));
    }
}
