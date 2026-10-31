<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelIncident extends Model
{
    protected $fillable = ['request_key', 'parcel_id', 'sorting_center_id', 'reported_by', 'type', 'previous_status', 'reason', 'evidence_path'];
    protected $hidden = ['evidence_path'];
    public function parcel() { return $this->belongsTo(Parcel::class); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Incident reports are append-only.'));
        static::deleting(fn () => throw new \LogicException('Incident reports are append-only.'));
    }
}
