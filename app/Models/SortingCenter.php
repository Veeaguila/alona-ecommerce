<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SortingCenter extends Model
{
    use HasFactory;

    public function scopeAvailableForEnrollment(Builder $query): void
    {
        $query->where('approval_status', 'approved')->whereHas('user', function ($user) {
            $user->where('usertype', 'sorting_center')->where('status', 'approved')->where(function ($verification) {
                $verification->whereDoesntHave('partnerApplication')->orWhereNotNull('email_verified_at');
            });
        });
    }

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
