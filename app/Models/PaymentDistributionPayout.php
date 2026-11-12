<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDistributionPayout extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['destination', 'payload_hash'];
    protected function casts(): array { return ['destination' => 'encrypted:array', 'paid_at' => 'datetime']; }
}
