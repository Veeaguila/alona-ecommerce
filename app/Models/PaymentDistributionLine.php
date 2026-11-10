<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDistributionLine extends Model
{
    protected $guarded = ['id'];
    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if (array_diff(array_keys($model->getDirty()), ['beneficiary_id', 'updated_at'])
                || ($model->isDirty('beneficiary_id') && $model->getOriginal('beneficiary_id'))) {
                throw new \LogicException('Allocations and verified beneficiaries are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Financial allocations cannot be deleted.'));
    }
    public function distribution() { return $this->belongsTo(PaymentDistribution::class, 'distribution_id'); }
}
