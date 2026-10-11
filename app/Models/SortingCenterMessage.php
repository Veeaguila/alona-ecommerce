<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SortingCenterMessage extends Model
{
    protected $table = 'sorting_center_messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'recipient_id',
        'body',
        'read_at',
        'user_id',
        'subject',
        'message',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SortingCenterConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
