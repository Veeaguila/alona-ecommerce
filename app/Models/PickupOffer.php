<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickupOffer extends Model
{
    protected $fillable = ['parcel_id', 'sorting_center_id', 'rider_id', 'kind', 'status', 'expires_at', 'accepted_at'];
    protected function casts(): array { return ['expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime']; }
}
