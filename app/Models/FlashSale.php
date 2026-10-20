<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSale extends Model
{
    protected $fillable = [
        'seller_id',
        'product_id',
        'flash_price',
        'flash_quantity',
        'sold_quantity',
        'slot',
        'starts_at',
        'ends_at',
        'ended_at',
    ];

    protected $appends = [
        'status_label',
        'remaining',
        'remaining_label',
        'countdown_seconds',
    ];

    protected function casts(): array
    {
        return [
            'flash_price' => 'decimal:2',
            'flash_quantity' => 'integer',
            'sold_quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->flash_quantity - (int) ($this->sold_quantity ?? 0));
    }

    public function isActiveNow(): bool
    {
        if ($this->ended_at !== null || $this->remainingQuantity() <= 0) {
            return false;
        }

        $now = now();

        return $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->lessThanOrEqualTo($now)
            && $this->ends_at->greaterThan($now);
    }

    public function isUpcoming(): bool
    {
        if ($this->ended_at !== null || $this->remainingQuantity() <= 0) {
            return false;
        }

        $now = now();

        return $this->starts_at !== null
            && $this->starts_at->greaterThan($now);
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->ended_at !== null || $this->remainingQuantity() <= 0) {
            return 'Ended';
        }

        $now = now();

        if ($this->ends_at && $this->ends_at->lessThanOrEqualTo($now)) {
            return 'Ended';
        }

        if ($this->starts_at && $this->starts_at->greaterThan($now)) {
            return 'Upcoming';
        }

        return 'Ongoing';
    }

    public function getRemainingAttribute(): int
    {
        return $this->remainingQuantity();
    }

    public function getRemainingLabelAttribute(): string
    {
        return $this->remainingQuantity() . ' left';
    }

    public function getCountdownSecondsAttribute(): int
    {
        if (! $this->ends_at || $this->ended_at !== null) {
            return 0;
        }

        $diff = now()->diffInSeconds($this->ends_at, false);

        return $diff > 0 ? (int) $diff : 0;
    }
}

