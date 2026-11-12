<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderPayBatch extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['proof_path', 'proof_sha256', 'request_hash', 'reference_hash'];
    protected function casts(): array { return ['amount_cents' => 'integer', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime', 'acknowledged_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(function (self $row) {
            $allowed = $row->getOriginal('status') === 'prepared'
                ? ['status', 'paid_by', 'payment_key', 'reference', 'reference_hash', 'proof_path', 'proof_sha256', 'paid_at', 'cancelled_at', 'updated_at']
                : ['acknowledged_at', 'updated_at'];
            if (array_diff(array_keys($row->getDirty()), $allowed)) throw new \LogicException('Payout history and recipient amounts cannot be rewritten.');
        });
        static::deleting(fn () => throw new \LogicException('Payment history cannot be deleted.'));
    }
}
