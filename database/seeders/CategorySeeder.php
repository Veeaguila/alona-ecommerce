<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Root / Top-Level Categories
        $roots = [
            'mens-fashion' => [
                'name' => "Men's Fashion",
                'image_path' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=85',
            ],
            'womens-fashion' => [
                'name' => "Women's Fashion",
                'image_path' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85',
            ],
            'babies-kids' => [
                'name' => 'Babies & Kids',
                'image_path' => 'https://images.unsplash.com/photo-1516627145497-ae6968895b74?auto=format&fit=crop&w=900&q=85',
            ],
            'electronics' => [
                'name' => 'Electronics & Mobiles',
                'image_path' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85',
            ],
            'laptops-computers' => [
                'name' => 'Laptops & Computers',
                'image_path' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=85',
            ],
            'home-living' => [
                'name' => 'Home & Living',
                'image_path' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85',
            ],
            'beauty' => [
                'name' => 'Health & Beauty',
                'image_path' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85',
            ],
            'groceries' => [
                'name' => 'Food & Groceries',
                'image_path' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=85',
            ],
            'jewelry' => [
                'name' => 'Jewelry & Watches',
                'image_path' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=900&q=85',
            ],
            'sports-travel' => [
                'name' => 'Sports & Travel',
                'image_path' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=85',
            ],
            'toys-games-collectibles' => [
                'name' => 'Toys, Games & Collectibles',
                'image_path' => 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=900&q=85',
            ],
            'hobbies-stationery' => [
                'name' => 'Hobbies & Stationery',
                'image_path' => 'https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?auto=format&fit=crop&w=900&q=85',
            ],
            'motors' => [
                'name' => 'Motors & Automotive',
                'image_path' => 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=900&q=85',
            ],
            'pet-care' => [
                'name' => 'Pet Care',
                'image_path' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=900&q=85',
            ],
        ];

        $rootModels = [];
        foreach ($roots as $slug => $data) {
            $rootModels[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'image_path' => $data['image_path'],
                    'parent_id' => null,
                    'is_active' => true,
                ]
            );
        }

        // 2. Subcategories with parent_id assignment
        $subcategories = [
            // Men's Fashion
            'mens-fashion' => [
                ['name' => "Men's Apparel", 'slug' => 'mens-apparel', 'image_path' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=85'],
                ['name' => "Men's Shoes", 'slug' => 'mens-shoes', 'image_path' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85'],
                ['name' => "Men's Bags & Accessories", 'slug' => 'mens-bags-accessories', 'image_path' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=85'],
            ],
            // Women's Fashion
            'womens-fashion' => [
                ['name' => "Women's Apparel", 'slug' => 'womens-apparel', 'image_path' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85'],
                ['name' => "Women's Shoes", 'slug' => 'womens-shoes', 'image_path' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=900&q=85'],
                ['name' => "Women's Bags", 'slug' => 'womens-bags', 'image_path' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Women Accessories', 'slug' => 'women-accessories', 'image_path' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=900&q=85'],
            ],
            // Babies & Kids
            'babies-kids' => [
                ['name' => 'Baby & Kids Clothing', 'slug' => 'baby-clothing', 'image_path' => 'https://images.unsplash.com/photo-1516627145497-ae6968895b74?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Baby Gear & Nursery', 'slug' => 'baby-gear-toys', 'image_path' => 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=900&q=85'],
            ],
            // Electronics
            'electronics' => [
                ['name' => 'Mobiles & Gadgets', 'slug' => 'mobiles-gadgets', 'image_path' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Mobiles Accessories', 'slug' => 'mobiles-accessories', 'image_path' => 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Smartwatches', 'slug' => 'smartwatches', 'image_path' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Audio', 'slug' => 'audio', 'image_path' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Cameras', 'slug' => 'cameras', 'image_path' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Drones', 'slug' => 'drones', 'image_path' => 'https://images.unsplash.com/photo-1508614589041-895b88991e3e?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Gaming', 'slug' => 'gaming', 'image_path' => 'https://images.unsplash.com/photo-1600080972464-8e5f35f63d08?auto=format&fit=crop&w=900&q=85'],
            ],
            // Laptops & Computers
            'laptops-computers' => [
                ['name' => 'Laptops & Notebooks', 'slug' => 'laptops', 'image_path' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Desktops & Monitors', 'slug' => 'desktops-monitors', 'image_path' => 'https://images.unsplash.com/photo-1547082299-de196ea013d6?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'PC Components', 'slug' => 'pc-components', 'image_path' => 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=900&q=85'],
            ],
            // Home & Living
            'home-living' => [
                ['name' => 'Furniture', 'slug' => 'furniture', 'image_path' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Home Appliances', 'slug' => 'home-appliances', 'image_path' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Kitchen & Dining', 'slug' => 'kitchen-dining', 'image_path' => 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Bedding', 'slug' => 'bedding', 'image_path' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Home Entertainment', 'slug' => 'home-entertainment', 'image_path' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=900&q=85'],
            ],
            // Health & Beauty
            'beauty' => [
                ['name' => 'Health & Personal Care', 'slug' => 'health-personal-care', 'image_path' => 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Makeup & Fragrances', 'slug' => 'makeup-fragrances', 'image_path' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85'],
            ],
            // Groceries
            'groceries' => [
                ['name' => 'Snacks', 'slug' => 'snacks', 'image_path' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Beverages', 'slug' => 'beverages', 'image_path' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Fresh Produce', 'slug' => 'fresh-produce', 'image_path' => 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Canned Goods', 'slug' => 'canned-goods', 'image_path' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&w=900&q=85'],
            ],
            // Jewelry
            'jewelry' => [
                ['name' => 'Fine & Fashion Jewelry', 'slug' => 'fine-jewelry', 'image_path' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Watches', 'slug' => 'watches', 'image_path' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=900&q=85'],
            ],
            // Sports & Travel
            'sports-travel' => [
                ['name' => 'Camping & Hiking', 'slug' => 'camping-hiking', 'image_path' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Fitness Equipment', 'slug' => 'fitness-equipment', 'image_path' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Sports Apparel & Shoes', 'slug' => 'sports-apparel', 'image_path' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=900&q=85'],
            ],
            // Toys & Games
            'toys-games-collectibles' => [
                ['name' => 'Action Figures & Collectibles', 'slug' => 'action-figures-collectibles', 'image_path' => 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Board Games & Puzzles', 'slug' => 'board-games-puzzles', 'image_path' => 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?auto=format&fit=crop&w=900&q=85'],
            ],
            // Hobbies & Stationery
            'hobbies-stationery' => [
                ['name' => 'Books & Magazines', 'slug' => 'books-magazines', 'image_path' => 'https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Office & School Supplies', 'slug' => 'office-stationery', 'image_path' => 'https://images.unsplash.com/photo-1456735190829-80ab873514b9?auto=format&fit=crop&w=900&q=85'],
            ],
            // Motors & Automotive
            'motors' => [
                ['name' => 'Auto Replacement & Parts', 'slug' => 'auto-parts', 'image_path' => 'https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Motorcycle Accessories & Gear', 'slug' => 'motorcycle-accessories', 'image_path' => 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=900&q=85'],
            ],
            // Pet Care
            'pet-care' => [
                ['name' => 'Pet Food & Treats', 'slug' => 'pet-food-treats', 'image_path' => 'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?auto=format&fit=crop&w=900&q=85'],
                ['name' => 'Pet Supplies & Accessories', 'slug' => 'pet-supplies-accessories', 'image_path' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=900&q=85'],
            ],
        ];

        foreach ($subcategories as $parentSlug => $subs) {
            $parent = $rootModels[$parentSlug] ?? Category::where('slug', $parentSlug)->first();
            if (! $parent) {
                continue;
            }

            foreach ($subs as $sub) {
                Category::updateOrCreate(
                    ['slug' => $sub['slug']],
                    [
                        'name' => $sub['name'],
                        'image_path' => $sub['image_path'],
                        'parent_id' => $parent->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
