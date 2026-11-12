<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDistribution extends Model
{
    protected $guarded = ['id'];
    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if (array_diff(array_keys($model->getDirty()), ['updated_at'])) throw new \LogicException('Order allocation snapshots are immutable. Append an adjustment instead.');
        });
        static::deleting(fn () => throw new \LogicException('Financial history cannot be deleted.'));
    }
    protected function casts(): array { return ['snapshot' => 'array']; }
    public function order() { return $this->belongsTo(Order::class); }
    public function lines() { return $this->hasMany(PaymentDistributionLine::class, 'distribution_id'); }
}
