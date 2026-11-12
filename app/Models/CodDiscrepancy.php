<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodDiscrepancy extends Model
{
    protected $fillable = ['cod_remittance_id', 'difference', 'reason', 'evidence_path', 'status', 'resolution', 'approved_by', 'approved_at'];
    protected $hidden = ['evidence_path'];
    protected function casts(): array { return ['difference' => 'decimal:2', 'approved_at' => 'immutable_datetime']; }
    public function remittance() { return $this->belongsTo(CodRemittance::class, 'cod_remittance_id'); }
    protected static function booted(): void
    {
        static::updating(function (self $row) {
            if ($row->getOriginal('status') !== 'pending' || $row->status !== 'approved'
                || $row->isDirty(['cod_remittance_id', 'difference', 'reason', 'evidence_path'])) {
                throw new \LogicException('A COD discrepancy can only receive one audited final approval.');
            }
        });
        static::deleting(fn () => throw new \LogicException('COD discrepancy history cannot be deleted.'));
    }
}
