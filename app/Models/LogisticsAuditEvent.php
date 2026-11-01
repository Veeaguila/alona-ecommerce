<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LogisticsAuditEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['event_key', 'actor_id', 'sorting_center_id', 'parcel_id', 'rider_id', 'action', 'data', 'created_at'];
    protected function casts(): array { return ['data' => 'array', 'created_at' => 'immutable_datetime']; }
    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    protected static function booted(): void
    {
        static::creating(function (self $event): void { $event->event_key ??= (string) Str::uuid(); });
        static::updating(fn () => throw new \LogicException('Logistics audit events are immutable.'));
        static::deleting(fn () => throw new \LogicException('Logistics audit events are immutable.'));
    }
}
