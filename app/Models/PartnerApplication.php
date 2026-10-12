<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerApplication extends Model
{
    protected $fillable = ['user_id', 'role', 'details', 'address', 'documents', 'terms_accepted_at', 'privacy_accepted_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'address' => 'array', 'documents' => 'array', 'terms_accepted_at' => 'datetime', 'privacy_accepted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
