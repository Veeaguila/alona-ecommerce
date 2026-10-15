<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'parcel_id',
        'seller_id',
        'sorting_center_id',
        'status',
        'scheduled_date',
        'time_slot',
        'carrier_partner',
        'notes',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'requested_at' => 'datetime',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(SortingCenter::class);
    }
}
