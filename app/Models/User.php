<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    // Basic account information
    'name',
    'last_name',
    'first_name',
    'middle_name',
    'middle_initial',
    'suffix',
    'sex',
    'email',
    'google_id',
    'avatar',

    // Personal information
    'contact_no',
    'birthday',
    'age',

    // Address information
    'address',
    'province',
    'municipality',
    'barangay',
    'street_address',
    'address_geography',

    // Registration documents
    'id_path',
    'business_permit_path',

    // Account / approval information
    'usertype',
    'status',
    'rejection_reason',
    'password',
    'otp_code',
    'otp_expires_at',
    'email_verified_at',

    // Seller store information
    'store_name',
    'store_description',
    'line_of_business',
    'store_logo_path',
    'store_banner_path',
    'store_tagline',
    'operating_hours',
    'response_time',
    'shipping_policy',
    'warranty_policy',
    'return_policy',
    'vacation_mode',
    'vacation_message',

    // Seller settings
    'shipping_fee',
    'return_policy_days',
    'bank_name',
    'bank_account_name',
    'bank_account_number',
    'notify_new_order',
    'notify_messages',
])]
#[Hidden([
    'password',
    'remember_token',
    'otp_code',
])]
class User extends Authenticatable implements MustVerifyEmail
{
    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if (! $user->isDirty('contact_no') || ! is_string($user->contact_no)
                || ! preg_match('/^(09[0-9]{9}|\\+639[0-9]{9})$/', $user->contact_no)) {
                return;
            }
            $mobile = \App\Services\LogisticsOnboarding::mobile($user->contact_no);
            $duplicate = self::where('id', '!=', $user->id ?? 0)
                ->whereIn('contact_no', [$mobile, '0'.substr($mobile, 3)])->exists();
            // Include reserved logistics identities even if contact details were later edited.
            $reserved = \Illuminate\Support\Facades\Schema::hasTable('logistics_registrations')
                && LogisticsRegistration::where('user_id', '!=', $user->id ?? 0)->where('mobile', $mobile)->exists();
            if ($duplicate || $reserved) {
                throw \Illuminate\Validation\ValidationException::withMessages(['contact_no' => 'This mobile number is already in use.']);
            }
        });
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Buyer relationships
    |--------------------------------------------------------------------------
    */

    public function sortingCenter(): HasOne
    {
        return $this->hasOne(SortingCenter::class);
    }

    public function partnerApplication(): HasOne
    {
        return $this->hasOne(PartnerApplication::class);
    }

    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function buyerNotifications(): HasMany
    {
        return $this->hasMany(BuyerNotification::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Seller relationships
    |--------------------------------------------------------------------------
    */

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'seller_id');
    }

    public function sellerNotifications(): HasMany
    {
        return $this->hasMany(SellerNotification::class);
    }

    public function conversationsAsBuyer(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function conversationsAsSeller(): HasMany
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function returnRequestsAsBuyer(): HasMany
    {
        return $this->hasMany(OrderReturnRequest::class, 'buyer_id');
    }

    public function returnRequestsAsSeller(): HasMany
    {
        return $this->hasMany(OrderReturnRequest::class, 'seller_id');
    }

    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function claimedVouchers()
    {
        return $this->belongsToMany(Voucher::class, 'user_vouchers')
            ->withPivot(['status', 'claimed_at', 'used_at', 'expiring_alert_sent_at'])
            ->withTimestamps();
    }

    public function sellerReviewsGiven(): HasMany
    {
        return $this->hasMany(SellerReview::class, 'user_id');
    }

    public function sellerReviewsReceived(): HasMany
    {
        return $this->hasMany(SellerReview::class, 'seller_id');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'seller_id')->latest();
    }

    public function refundLedgersAsSeller(): HasMany
    {
        return $this->hasMany(RefundLedger::class, 'seller_id')->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute casting
    |--------------------------------------------------------------------------
    */

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address_geography' => 'array',
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',

            // Personal information
            'birthday' => 'date',
            'age' => 'integer',

            // Password
            'password' => 'hashed',

            // Seller settings
            'shipping_fee' => 'decimal:2',
            'return_policy_days' => 'integer',
            'notify_new_order' => 'boolean',
            'notify_messages' => 'boolean',

            // Store management
            'operating_hours' => 'array',
            'vacation_mode' => 'boolean',
        ];
    }

    /**
     * Generate a 6-digit numeric OTP valid for 5 minutes and send the verification email.
     */
    public function generateAndSendOtp(): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(5),
        ])->save();

        try {
            $this->notify(new \App\Notifications\BuyerOtpVerificationNotification($otp));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send Buyer OTP email: ' . $e->getMessage());
        }

        return $otp;
    }

    /*
    |--------------------------------------------------------------------------
    | Store Management Helpers (SELLER-62 to SELLER-65)
    |--------------------------------------------------------------------------
    */

    public static function getDefaultOperatingHours(): array
    {
        return [
            'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
            'tuesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
            'wednesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
            'thursday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
            'friday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00'],
            'saturday' => ['is_open' => true, 'open' => '09:00', 'close' => '17:00'],
            'sunday' => ['is_open' => false, 'open' => '09:00', 'close' => '17:00'],
        ];
    }

    public function getResponseTimeLabel(): string
    {
        return match ($this->response_time) {
            'within_hour' => 'Replies within 1 hour',
            'few_hours' => 'Replies within a few hours',
            'next_day' => 'Replies within 24 hours',
            default => 'Replies within the day',
        };
    }

    public function isStoreOpenNow(): bool
    {
        if ($this->vacation_mode) {
            return false;
        }

        $hours = $this->operating_hours ?: self::getDefaultOperatingHours();
        $today = strtolower(now()->format('l'));

        $schedule = $hours[$today] ?? null;
        if (!$schedule || empty($schedule['is_open'])) {
            return false;
        }

        $nowTime = now()->format('H:i');
        $openTime = $schedule['open'] ?? '09:00';
        $closeTime = $schedule['close'] ?? '18:00';

        return $nowTime >= $openTime && $nowTime <= $closeTime;
    }

    public function getStoreStatus(): array
    {
        if ($this->vacation_mode) {
            return [
                'is_open' => false,
                'is_vacation' => true,
                'status_label' => 'On Vacation',
                'detail' => $this->vacation_message ?: 'Orders temporarily paused.',
                'response_time_label' => $this->getResponseTimeLabel(),
            ];
        }

        $hours = $this->operating_hours ?: self::getDefaultOperatingHours();
        $today = strtolower(now()->format('l'));
        $schedule = $hours[$today] ?? null;

        $isOpen = $this->isStoreOpenNow();

        if ($isOpen && $schedule) {
            $closeFormatted = date('g:i A', strtotime($schedule['close']));
            $detail = "Open until {$closeFormatted}";
        } elseif ($schedule && !empty($schedule['is_open'])) {
            $openFormatted = date('g:i A', strtotime($schedule['open']));
            $detail = "Opens at {$openFormatted}";
        } else {
            $detail = 'Closed today';
        }

        return [
            'is_open' => $isOpen,
            'is_vacation' => false,
            'status_label' => $isOpen ? 'Open Now' : 'Closed',
            'detail' => $detail,
            'response_time_label' => $this->getResponseTimeLabel(),
        ];
    }
}
