<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    protected $fillable = [
        'name',
        'province',
        'base_shipping_rate',
        'per_kg_rate',
        'estimated_days',
        'is_active',
        'sorting_center_id',
    ];

    protected function casts(): array
    {
        return [
            'base_shipping_rate' => 'decimal:2',
            'per_kg_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(SortingCenter::class);
    }
}

