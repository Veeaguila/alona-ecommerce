<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderPayDestination extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['instructions'];
    protected function casts(): array { return ['instructions' => 'encrypted:array', 'created_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Receiving instructions are append-only. Add a new destination.'));
        static::deleting(fn () => throw new \LogicException('Receiving history cannot be deleted.'));
    }
}
