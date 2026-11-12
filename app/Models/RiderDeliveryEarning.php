<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderDeliveryEarning extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'earned_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->getOriginal('payout_status') === 'paid' && $row->isDirty()) {
                throw new \LogicException('A recorded payout cannot be rewritten.');
            }
            if (array_diff(array_keys($row->getDirty()), ['payout_status', 'paid_at', 'recorded_by', 'manual_reference', 'settlement_key', 'updated_at'])) {
                throw new \LogicException('Rider earning facts are immutable.');
            }
        });
    }

    public function charge(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SellerDeliveryCharge::class, 'seller_delivery_charge_id');
    }
}
