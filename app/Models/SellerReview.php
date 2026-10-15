<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerReview extends Model
{
    protected $fillable = [
        'user_id',
        'seller_id',
        'order_id',
        'rating',
        'comment',
        'media',
        'seller_reply',
        'seller_replied_at',
        'is_reported',
        'report_reason',
        'report_notes',
        'report_status',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'media' => 'array',
            'seller_replied_at' => 'datetime',
            'is_reported' => 'boolean',
            'reported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

