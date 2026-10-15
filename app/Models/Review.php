<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}