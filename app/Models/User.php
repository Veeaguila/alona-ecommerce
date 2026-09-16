<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    // Basic account information
    'name',
    'last_name',
    'first_name',
    'middle_initial',
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

    // Registration documents
    'id_path',
    'business_permit_path',

    // Account / approval information
    'usertype',
    'status',
    'password',

    // Seller store information
    'store_name',
    'store_description',
    'line_of_business',
    'store_logo_path',

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
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Buyer relationships
    |--------------------------------------------------------------------------
    */

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
            'email_verified_at' => 'datetime',

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
        ];
    }
}
