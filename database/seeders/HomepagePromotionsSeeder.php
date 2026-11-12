<?php

namespace Database\Seeders;

use App\Models\FlashSale;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\SellerPromotion;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class HomepagePromotionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Hero Promotional Graphic Banners that auto-change / auto-slide
        $heroBanners = [
            [
                'id' => 'banner_new_arrival',
                'is_active' => true,
                'layout_type' => 'full',
                'overlay_text' => false,
                'theme' => 'dark',
                'title' => 'New Arrival Autumn Winter Collection',
                'image_path' => '/storage/banners/ltOJYRn6L7iH0YEXIJxtWTaILCMq1PV997gtNE9n.jpg',
                'primary_button_url' => '/products?category=mens-fashion',
            ],
            [
                'id' => 'banner_audio_wrap',
                'is_active' => true,
                'layout_type' => 'full',
                'overlay_text' => false,
                'theme' => 'indigo',
                'title' => 'Wrap Yourself in Sound',
                'image_path' => '/storage/banners/4THZebyVbYah4L8uS54kKvJMVzMy6h05cyswAnty.jpg',
                'primary_button_url' => '/products?category=audio',
            ],
            [
                'id' => 'banner_elegance_fashion',
                'is_active' => true,
                'layout_type' => 'full',
                'overlay_text' => false,
                'theme' => 'rose',
                'title' => 'In Every Stitch Elegance',
                'image_path' => '/storage/banners/ksfuzmbTwr1IiZmIR7eLjeVPE44XJ16bVrhTmbsN.jpg',
                'primary_button_url' => '/products?category=womens-fashion',
            ],
            [
                'id' => 'banner_smart_tech',
                'is_active' => true,
                'layout_type' => 'full',
                'overlay_text' => false,
                'theme' => 'indigo',
                'title' => 'Smart Tech & Gadgets',
                'image_path' => '/storage/banners/2zjgmwkxz9Loj8mHWiePL7DzkGpKukJk4AKHNbrF.png',
                'primary_button_url' => '/products?category=laptops-computers',
            ],
            [
                'id' => 'banner_doritos_crunch',
                'is_active' => true,
                'layout_type' => 'full',
                'overlay_text' => false,
                'theme' => 'amber',
                'title' => "Can't Resist The Crunch",
                'image_path' => '/storage/banners/0uT959TjZOtJO42RpRuesYbh9gCcIOm2xPDWymqG.jpg',
                'primary_button_url' => '/products?category=snacks',
            ],
        ];

        PlatformSetting::updateOrCreate(
            ['key' => 'home.hero_banners'],
            [
                'value' => json_encode($heroBanners),
                'description' => 'Rotating Promotional Hero Banners for Homepage Carousel',
                'is_active' => true,
            ]
        );
        $this->command->info('Hero rotating promotional banners seeded successfully.');

        // 2. Active Flash Sales with countdown timer
        FlashSale::query()->delete();

        $flashProducts = Product::where('status', 'approved')
            ->where('stock', '>', 5)
            ->inRandomOrder()
            ->take(8)
            ->get();

        $slots = ['09:00–15:00', '15:00–21:00', '21:00–03:00'];
        $endsAt = now()->addHours(6)->addMinutes(30);

        foreach ($flashProducts as $idx => $prod) {
            $origPrice = (float) $prod->price;
            $discountPercent = rand(25, 45);
            $flashPrice = round($origPrice * (1 - ($discountPercent / 100)), 2);
            $qty = min($prod->stock, rand(15, 30));
            $sold = rand(3, max(4, (int) round($qty * 0.6)));

            FlashSale::create([
                'seller_id' => $prod->seller_id,
                'product_id' => $prod->id,
                'flash_price' => $flashPrice,
                'flash_quantity' => $qty,
                'sold_quantity' => $sold,
                'slot' => $slots[$idx % count($slots)],
                'starts_at' => now()->subHours(2),
                'ends_at' => $endsAt,
                'ended_at' => null,
            ]);
        }
        $this->command->info("Seeded {$flashProducts->count()} active Flash Sale items with countdown timer.");

        // 3. Active Bundle Deals & Seller Promotions
        SellerPromotion::query()->delete();

        $sellers = User::where('usertype', 'seller')->get();
        foreach ($sellers as $seller) {
            $sellerProductIds = Product::where('seller_id', $seller->id)
                ->where('status', 'approved')
                ->take(4)
                ->pluck('id')
                ->all();

            if (!empty($sellerProductIds)) {
                SellerPromotion::create([
                    'seller_id' => $seller->id,
                    'name' => ($seller->store_name ?: $seller->name) . ' Special Bundle: Buy 2 Save 15%',
                    'type' => 'bundle',
                    'product_ids' => $sellerProductIds,
                    'min_quantity' => 2,
                    'discount_type' => 'percentage',
                    'discount_value' => 15.00,
                    'is_active' => true,
                    'starts_at' => now()->subDays(2),
                    'ends_at' => now()->addDays(30),
                ]);
            }
        }
        $this->command->info('Seeded active Bundle Deals / Seller Promotions.');

        // 4. Platform Vouchers
        Voucher::where('is_platform', true)->delete();
        $vouchers = [
            [
                'code' => 'ALONA50',
                'type' => 'fixed',
                'value' => 50.00,
                'min_spend' => 300.00,
                'usage_limit' => 1000,
                'per_customer_limit' => 1,
            ],
            [
                'code' => 'FREESHIP',
                'type' => 'fixed',
                'value' => 60.00,
                'min_spend' => 200.00,
                'usage_limit' => 500,
                'per_customer_limit' => 2,
            ],
            [
                'code' => 'MEGA10',
                'type' => 'percentage',
                'value' => 10.00,
                'max_discount' => 150.00,
                'min_spend' => 500.00,
                'usage_limit' => 300,
                'per_customer_limit' => 1,
            ],
        ];

        foreach ($vouchers as $v) {
            Voucher::create([
                'is_platform' => true,
                'seller_id' => null,
                'code' => $v['code'],
                'type' => $v['type'],
                'value' => $v['value'],
                'max_discount' => $v['max_discount'] ?? null,
                'min_spend' => $v['min_spend'],
                'usage_limit' => $v['usage_limit'],
                'per_customer_limit' => $v['per_customer_limit'],
                'used_count' => rand(5, 40),
                'starts_at' => now()->subDays(5)->toDateString(),
                'expires_at' => now()->addDays(30)->toDateString(),
                'is_active' => true,
            ]);
        }
        $this->command->info('Seeded active Platform Vouchers.');
    }
}
