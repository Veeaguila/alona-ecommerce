<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderPayEarning extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount_cents' => 'integer', 'rate_revision' => 'integer', 'snapshot' => 'array', 'earned_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(function (self $row) {
            $allowed = ['batch_id', 'updated_at'];
            if ($row->getOriginal('status') === 'pending') $allowed = array_merge($allowed, ['status', 'active_key', 'earned_at', 'cancelled_at']);
            if (array_diff(array_keys($row->getDirty()), $allowed)) throw new \LogicException('Earning amounts, recipients and completed work are immutable.');
        });
        static::deleting(fn () => throw new \LogicException('Earning history cannot be deleted.'));
    }
}
