<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'seller_id', 'is_platform', 'code', 'type', 'value', 'max_discount', 'min_spend',
        'usage_limit', 'per_customer_limit', 'target_type', 'targeted_product_ids', 'targeted_category_ids',
        'used_count', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_platform' => 'boolean',
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'targeted_product_ids' => 'array',
            'targeted_category_ids' => 'array',
            'used_count' => 'integer',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'voucher_id');
    }

    public function userVouchers()
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function claimedUsers()
    {
        return $this->belongsToMany(User::class, 'user_vouchers')
            ->withPivot(['status', 'claimed_at', 'used_at', 'expiring_alert_sent_at'])
            ->withTimestamps();
    }

    public function isValidNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function isExpiringSoon(): bool
    {
        if (! $this->is_active || ! $this->expires_at) {
            return false;
        }

        $now = now();
        $expiry = $this->expires_at->copy()->endOfDay();

        return $expiry->isFuture() && $now->diffInHours($expiry, false) <= 48 && $now->diffInHours($expiry, false) >= 0;
    }

    public function discountFor(float $subtotal, float $shippingFee = 0): float
    {
        if ($this->min_spend && $subtotal < (float) $this->min_spend) {
            return 0;
        }

        if ($this->type === 'free_shipping') {
            $shipping = max(0, (float) $shippingFee);
            if ($this->max_discount !== null && (float) $this->max_discount > 0) {
                return min($shipping, (float) $this->max_discount);
            }
            return $shipping;
        }

        if ($this->type === 'percentage') {
            $discount = round($subtotal * ((float) $this->value / 100), 2);
            if ($this->max_discount !== null && (float) $this->max_discount > 0) {
                $discount = min($discount, (float) $this->max_discount);
            }
            return min($discount, $subtotal);
        }

        return min((float) $this->value, $subtotal);
    }
}
