<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SortingCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'permit_doc_url',
        'id_doc_url',
        'approval_status',
    ];

    protected function casts(): array
    {
        return [
            'approval_status' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}
