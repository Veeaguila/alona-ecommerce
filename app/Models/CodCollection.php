<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodCollection extends Model
{
    public $timestamps = false;
    protected $fillable = ['request_key', 'order_id', 'parcel_id', 'rider_id', 'sorting_center_id', 'amount', 'evidence_path', 'collected_at', 'created_at'];
    protected $hidden = ['evidence_path'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'collected_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Recorded COD collections are immutable.'));
        static::deleting(fn () => throw new \LogicException('Recorded COD collections are immutable.'));
    }
    public function parcel() { return $this->belongsTo(Parcel::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function rider() { return $this->belongsTo(Rider::class); }
}
