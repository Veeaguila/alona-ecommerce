<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelHandover extends Model
{
    protected $fillable = ['request_key', 'parcel_id', 'sorting_center_id', 'from_rider_id', 'to_rider_id', 'requested_by',
        'leg', 'status', 'reason', 'parcel_ids', 'source_acknowledged_at', 'target_acknowledged_at', 'confirmed_by', 'confirmed_at'];
    protected function casts(): array { return ['parcel_ids' => 'array', 'source_acknowledged_at' => 'immutable_datetime',
        'target_acknowledged_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime']; }
    public function parcel() { return $this->belongsTo(Parcel::class); }
    public function fromRider() { return $this->belongsTo(Rider::class, 'from_rider_id'); }
    public function toRider() { return $this->belongsTo(Rider::class, 'to_rider_id'); }
}
