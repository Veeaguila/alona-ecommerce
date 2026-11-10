<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_type_id',
        'value',
        'label',
        'meta',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'meta' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function variantType(): BelongsTo
    {
        return $this->belongsTo(VariantType::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('value');
    }

    public function getHexColorAttribute(): ?string
    {
        return $this->meta['hex'] ?? null;
    }
}
