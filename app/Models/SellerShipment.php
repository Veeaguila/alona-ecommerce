<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerShipment extends Model
{
    protected $fillable = ['order_id', 'seller_id', 'assigned_rider_id', 'sorting_center_id', 'dispatched_at', 'completed_at'];

    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
