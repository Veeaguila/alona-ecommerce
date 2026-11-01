<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

class ParcelHubJourney extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['route_snapshot' => 'array', 'origin_received_at' => 'datetime', 'destination_received_at' => 'datetime'];
    }
    public function parcel(): BelongsTo { return $this->belongsTo(Parcel::class); }
    public function originHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'origin_hub_id'); }
    public function destinationHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'destination_hub_id'); }
    public function transfer(): HasOne { return $this->hasOne(HubTransfer::class); }
}
