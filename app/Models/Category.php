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
     * Get effective variant types (inheriting from parent if subcategory has none mapped,
     * or resolving by canonical category taxonomy matching).
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
                $types = $parent->activeVariantTypes()->with('activeValues')->get();
            }
        }

        // Dynamic taxonomy fallback if category or database was not seeded with mappings
        if ($types->isEmpty()) {
            $targetSlugs = $this->resolveFallbackVariantTypeSlugs();
            if (! empty($targetSlugs)) {
                $types = \App\Models\VariantType::query()
                    ->whereIn('slug', $targetSlugs)
                    ->where('is_active', true)
                    ->with('activeValues')
                    ->get();

                // Sort according to targetSlugs order
                $types = $types->sortBy(function ($t) use ($targetSlugs) {
                    $idx = array_search($t->slug, $targetSlugs);
                    return $idx !== false ? $idx : 999;
                })->values();

                // Auto-persist into category_variant_types so future queries are instant
                try {
                    foreach ($types as $idx => $t) {
                        \Illuminate\Support\Facades\DB::table('category_variant_types')->updateOrInsert(
                            [
                                'category_id' => $this->id,
                                'variant_type_id' => $t->id,
                            ],
                            [
                                'is_required' => false,
                                'sort_order' => $idx + 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    // Suppress if readonly or transaction lock
                }
            }
        }

        return $types;
    }

    /**
     * Determine canonical variant type slugs by examining category name and slug.
     */
    public function resolveFallbackVariantTypeSlugs(): array
    {
        $term = strtolower(trim(($this->slug ?? '') . ' ' . ($this->name ?? '')));

        // 1. Fashion & Apparel
        if (preg_match('/(shoe|footwear|sneaker|boot|sandal|slipper)/', $term)) {
            $isWomen = preg_match('/(women|lady|female|girl)/', $term);
            return [
                'shoe-size-eu',
                $isWomen ? 'shoe-size-us-women' : 'shoe-size-us-men',
                'shoe-size-cm',
                'color',
                'material-shoes',
                'shoe-width',
                'shoe-style',
            ];
        }

        if (preg_match('/(apparel|clothing|fashion|shirt|t-shirt|pant|jeans|dress|jacket|hoodie|coat|suit|underwear|sleepwear)/', $term)) {
            return [
                'size-international',
                'color',
                'size-waist',
                'size-numeric',
                'material-fashion',
                'fit',
                'sleeve-length',
                'pattern',
            ];
        }

        if (preg_match('/(bag|backpack|luggage|wallet|purse|tote)/', $term)) {
            return ['bag-size', 'color', 'material-fashion', 'bag-style'];
        }

        if (preg_match('/(jewelry|watch|accessory|necklace|bracelet|ring|earring)/', $term)) {
            return ['jewelry-metal', 'jewelry-finish', 'ring-size', 'watch-strap', 'color'];
        }

        // 2. Electronics & Tech
        if (preg_match('/(mobile|phone|smartphone|tablet|ipad|cellular)/', $term)) {
            return ['ram-mobile', 'storage-mobile', 'color', 'connectivity-mobile', 'device-condition'];
        }

        if (preg_match('/(laptop|notebook|computer|desktop|pc|macbook)/', $term)) {
            return ['ram-laptop', 'storage-laptop', 'storage-type', 'processor-family', 'display-size-screen', 'color', 'os'];
        }

        if (preg_match('/(component|hardware|processor|cpu|gpu|ram|motherboard|psu|power supply|storage drive|ssd|hdd)/', $term)) {
            return ['ram-component-capacity', 'ram-type', 'ram-speed', 'storage-component-capacity', 'storage-interface', 'psu-wattage', 'motherboard-form-factor'];
        }

        if (preg_match('/(headphone|earphone|audio|speaker|headset|earbuds)/', $term)) {
            return ['audio-connectivity', 'audio-style', 'color', 'microphone-option'];
        }

        // 3. Groceries & Consumables
        if (preg_match('/(beverage|drink|juice|coffee|tea|water|soda)/', $term)) {
            return ['flavor', 'liquid-volume', 'pack-size'];
        }

        if (preg_match('/(food|grocery|snack|candy|chocolate|bakery|canned|pantry|condiment|seasoning)/', $term)) {
            return ['flavor', 'food-weight', 'spice-level', 'pack-size'];
        }

        // 4. Beauty & Personal Care
        if (preg_match('/(beauty|makeup|skincare|cosmetic|lotion|serum|lipstick|foundation|fragrance|perfume)/', $term)) {
            return ['shade-color', 'skin-type', 'beauty-volume', 'formulation', 'scent-profile'];
        }

        // 5. Home, Living & Furniture
        if (preg_match('/(furniture|chair|table|desk|bed|sofa|cabinet|shelf|dining|living|decor|curtain|bedding)/', $term)) {
            return ['size-general', 'material-furniture', 'furniture-finish', 'color'];
        }

        // 6. Sports & Fitness
        if (preg_match('/(sport|fitness|gym|workout|exercise|athletic|yoga)/', $term)) {
            return ['size-international', 'color', 'resistance-weight', 'material-fashion'];
        }

        // 7. Babies & Kids
        if (preg_match('/(baby|infant|kid|toddler|children)/', $term)) {
            return ['size-children', 'color', 'material-fashion', 'pattern'];
        }

        // 8. Pet Care
        if (preg_match('/(pet|dog|cat|puppy|kitten)/', $term)) {
            return ['pet-size', 'flavor', 'food-weight', 'color'];
        }

        // 9. Motors & Automotive
        if (preg_match('/(motor|auto|car|vehicle|motorcycle|parts)/', $term)) {
            return ['vehicle-brand', 'color', 'material-furniture'];
        }

        // Universal Fallback
        return ['color', 'size-international', 'size-general'];
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
        $pending = $ids;

        while ($pending !== []) {
            $pending = static::query()
                ->whereIn('parent_id', $pending)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->all();
            $ids = array_merge($ids, $pending);
        }

        return $ids;
    }
}
