<?php

namespace App\Services;

use App\Models\PlatformSetting;

class FooterService
{
    /**
     * Default footer structure matching the marketplace specifications.
     *
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return [
            'theme' => 'light',
            'brand' => [
                'tagline' => 'Discover products, connect with sellers, and enjoy simple shopping with Alona.',
                'copyright' => '© ' . date('Y') . ' Alona. All rights reserved.',
                'subtext' => 'A marketplace made simple.',
            ],
            'sections' => [
                [
                    'id' => 'shop',
                    'title' => 'Shop',
                    'icon' => 'bag',
                    'links' => [
                        ['label' => 'All Products', 'url' => '/products'],
                        ['label' => 'Categories', 'url' => '/categories'],
                        ['label' => 'Cart', 'url' => '/buyer/cart'],
                        ['label' => 'Track My Order', 'url' => '/buyer/orders'],
                    ],
                ],
                [
                    'id' => 'customer-support',
                    'title' => 'Customer Support',
                    'icon' => 'headphones',
                    'links' => [
                        ['label' => 'Help Center', 'url' => '/help'],
                        ['label' => 'Frequently Asked Questions', 'url' => '/faq'],
                        ['label' => 'Contact Us', 'url' => '/help'],
                        ['label' => 'Complaints & Ticket Status', 'url' => '/buyer/complaints'],
                        ['label' => 'Buyer Protection', 'url' => '/policies'],
                    ],
                ],
                [
                    'id' => 'partner-account',
                    'title' => 'Partner & Account',
                    'icon' => 'user',
                    'links' => [
                        ['label' => 'Become a Seller', 'url' => '/register/seller'],
                        ['label' => 'Become a Rider', 'url' => '/register'],
                        ['label' => 'Logistics Partner', 'url' => '/register/logistics'],
                        ['label' => 'My Account', 'url' => '/buyer/dashboard'],
                    ],
                ],
                [
                    'id' => 'policies',
                    'title' => 'Policies & Information',
                    'icon' => 'shield',
                    'links' => [
                        ['label' => 'Privacy Policy', 'url' => '/policies#privacy'],
                        ['label' => 'Terms & Conditions', 'url' => '/policies#terms'],
                        ['label' => 'Shipping & Delivery Policy', 'url' => '/policies#shipping'],
                        ['label' => 'Returns & Refunds Policy', 'url' => '/policies#returns'],
                        ['label' => 'Payment Methods', 'url' => '/policies?policy=payment'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the active footer configuration from database or default.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        try {
            $setting = PlatformSetting::where('key', 'marketplace.footer')->first();
            if ($setting && $setting->value) {
                $decoded = json_decode($setting->value, true);
                if (is_array($decoded) && !empty($decoded['sections'])) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            // Return default config on error
        }

        return self::defaultConfig();
    }
}

