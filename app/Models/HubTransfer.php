<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubTransfer extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['dispatched_at' => 'datetime', 'received_at' => 'datetime']; }
}
