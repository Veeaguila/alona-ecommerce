<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelDeliveryAttempt extends Model
{
    public $timestamps = false;
    protected $fillable = ['parcel_id', 'rider_id', 'attempt_number', 'reason', 'evidence_path', 'created_at'];
    protected $hidden = ['evidence_path'];
    protected function casts(): array { return ['created_at' => 'immutable_datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Delivery attempt records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Delivery attempt records are immutable.'));
    }
}
