<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class RiderDecisionAudit extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'sorting_center_id', 'rider_id', 'actor_user_id', 'actor_name',
        'action', 'old_state', 'new_state', 'reason', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'sorting_center_id' => 'integer', 'rider_id' => 'integer', 'actor_user_id' => 'integer',
            'old_state' => 'array', 'new_state' => 'array', 'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Rider decision audits are append-only.');
        });
        static::deleting(function (): void {
            throw new LogicException('Rider decision audits are append-only.');
        });
    }
}
