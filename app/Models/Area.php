<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'sorting_center_id',
        'name',
    ];

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(SortingCenter::class);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'sorted_area_id');
    }

    public function riders(): BelongsToMany
    {
        return $this->belongsToMany(Rider::class, 'rider_areas');
    }
}
