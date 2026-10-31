<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelReturn extends Model
{
    protected $fillable = ['parcel_id', 'sorting_center_id', 'rider_id', 'status', 'reason', 'picked_up_at', 'handed_over_at', 'seller_received_at', 'restocked_at'];
    protected function casts(): array { return ['picked_up_at' => 'immutable_datetime', 'handed_over_at' => 'immutable_datetime',
        'seller_received_at' => 'immutable_datetime', 'restocked_at' => 'immutable_datetime']; }
    public function parcel() { return $this->belongsTo(Parcel::class); }
    public function rider() { return $this->belongsTo(Rider::class); }
}
