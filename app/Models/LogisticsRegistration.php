<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsRegistration extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['otp_hash', 'request_key'];

    protected function casts(): array
    {
        return ['otp_expires_at' => 'datetime', 'otp_attempts' => 'integer'];
    }
}
