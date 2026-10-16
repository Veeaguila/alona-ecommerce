<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDeliveryCharge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'logistics_amount' => 'decimal:2', 'rider_amount' => 'decimal:2',
            'recognized_at' => 'datetime', 'collected_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->getOriginal('collection_status') === 'collected' && $row->isDirty()) {
                throw new \LogicException('A recorded collection cannot be rewritten.');
            }
            if (array_diff(array_keys($row->getDirty()), ['collection_status', 'collected_at', 'recorded_by', 'manual_reference', 'settlement_key', 'updated_at'])) {
                throw new \LogicException('Delivery charge facts are immutable.');
            }
        });
    }
}
