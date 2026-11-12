<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodRemittance extends Model
{
    protected $fillable = ['request_key', 'rider_id', 'sorting_center_id', 'submitted_amount', 'received_amount', 'evidence_path',
        'receipt_evidence_path', 'status', 'confirmed_by', 'confirmed_at'];
    protected $hidden = ['evidence_path', 'receipt_evidence_path'];
    protected function casts(): array { return ['submitted_amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'confirmed_at' => 'immutable_datetime']; }
    public function rider() { return $this->belongsTo(Rider::class); }
    public function discrepancy() { return $this->hasOne(CodDiscrepancy::class); }
    protected static function booted(): void
    {
        static::updating(function (self $row) {
            if ($row->getOriginal('status') !== 'submitted' || $row->status !== 'confirmed'
                || $row->isDirty(['request_key', 'rider_id', 'sorting_center_id', 'submitted_amount', 'evidence_path'])) {
                throw new \LogicException('COD remittances can only be explicitly confirmed once.');
            }
        });
        static::deleting(fn () => throw new \LogicException('COD remittance history cannot be deleted.'));
    }
}
