<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerNotification extends Model
{
    protected $table = 'seller_notifications';

    protected $fillable = [
        'user_id',
        'seller_id',
        'order_id',
        'type',
        'title',
        'message',
        'url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}