<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsHub extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pickup_city_codes' => 'array', 'delivery_city_codes' => 'array', 'is_active' => 'boolean'];
    }

    public function sortingCenter(): BelongsTo { return $this->belongsTo(SortingCenter::class); }
}
