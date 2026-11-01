<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderPayTrip extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['manifest' => 'array', 'dispatched_at' => 'datetime', 'completed_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(function (self $row) {
            if (array_diff(array_keys($row->getDirty()), ['completed_at'])) throw new \LogicException('A dispatched trip manifest cannot be rewritten.');
        });
        static::deleting(fn () => throw new \LogicException('Trip history cannot be deleted.'));
    }
}
