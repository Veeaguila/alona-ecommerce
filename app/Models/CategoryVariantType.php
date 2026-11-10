<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CategoryVariantType extends Pivot
{
    protected $table = 'category_variant_types';

    protected $fillable = [
        'category_id',
        'variant_type_id',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];
}
