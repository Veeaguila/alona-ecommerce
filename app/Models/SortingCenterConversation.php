<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SortingCenterConversation extends Model
{
    protected $table = 'sorting_center_conversations';

    protected $fillable = [
        'sorting_center_id',
        'created_by',
    ];

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(SortingCenter::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sorting_center_conversation_participants', 'conversation_id', 'user_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SortingCenterMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SortingCenterMessage::class, 'conversation_id')->latestOfMany();
    }
}
