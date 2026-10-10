<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => "Men's Apparel",
                'slug' => 'mens-apparel',
                'image_path' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Mobiles & Gadgets',
                'slug' => 'mobiles-gadgets',
                'image_path' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Mobiles Accessories',
                'slug' => 'mobiles-accessories',
                'image_path' => 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Home Entertainment',
                'slug' => 'home-entertainment',
                'image_path' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Babies & Kids',
                'slug' => 'babies-kids',
                'image_path' => 'https://images.unsplash.com/photo-1516627145497-ae6968895b74?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'image_path' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Groceries',
                'slug' => 'groceries',
                'image_path' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Toys, Games & Collectibles',
                'slug' => 'toys-games-collectibles',
                'image_path' => 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => "Women's Bags",
                'slug' => 'womens-bags',
                'image_path' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Women Accessories',
                'slug' => 'women-accessories',
                'image_path' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => "Women's Apparel",
                'slug' => 'womens-apparel',
                'image_path' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Health & Personal Care',
                'slug' => 'health-personal-care',
                'image_path' => 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Makeup & Fragrances',
                'slug' => 'makeup-fragrances',
                'image_path' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Home Appliances',
                'slug' => 'home-appliances',
                'image_path' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Laptops & Computers',
                'slug' => 'laptops-computers',
                'image_path' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Cameras',
                'slug' => 'cameras',
                'image_path' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Sports & Travel',
                'slug' => 'sports-travel',
                'image_path' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => "Men's Bags & Accessories",
                'slug' => 'mens-bags-accessories',
                'image_path' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => "Men's Shoes",
                'slug' => 'mens-shoes',
                'image_path' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Motors',
                'slug' => 'motors',
                'image_path' => 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => "Women's Shoes",
                'slug' => 'womens-shoes',
                'image_path' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Pet Care',
                'slug' => 'pet-care',
                'image_path' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Audio',
                'slug' => 'audio',
                'image_path' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Hobbies & Stationery',
                'slug' => 'hobbies-stationery',
                'image_path' => 'https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Gaming',
                'slug' => 'gaming',
                'image_path' => 'https://images.unsplash.com/photo-1600080972464-8e5f35f63d08?auto=format&fit=crop&w=900&q=85',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                [
                    'slug' => $category['slug'],
                ],
                [
                    'name' => $category['name'],
                    'image_path' => $category['image_path'],
                    'is_active' => true,
                ]
            );
        }
    }
}