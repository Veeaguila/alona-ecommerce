<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sorting_center_id',
        'name',
        'contact',
        'sex',
        'birthday',
        'province',
        'municipality',
        'barangay',
        'street',
        'house_number',
        'vehicle_type',
        'plate_number',
        'or_cr_doc_url',
        'license_doc_url',
        'approval_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'approval_status' => 'string',
            'birthday' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['age'];

    public function getAgeAttribute(): ?int
    {
        return $this->birthday?->age;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(SortingCenter::class);
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'rider_areas');
    }

    public function assignedParcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'assigned_rider_id');
    }
}
