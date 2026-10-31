<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderPaySetting extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['enabled' => 'boolean', 'pickup_cents' => 'integer', 'delivery_cents' => 'integer', 'transfer_cents' => 'integer', 'revision' => 'integer']; }
}
