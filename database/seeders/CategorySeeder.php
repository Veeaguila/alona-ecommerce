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
            ],
            [
                'name' => 'Mobiles & Gadgets',
                'slug' => 'mobiles-gadgets',
            ],
            [
                'name' => 'Mobiles Accessories',
                'slug' => 'mobiles-accessories',
            ],
            [
                'name' => 'Home Entertainment',
                'slug' => 'home-entertainment',
            ],
            [
                'name' => 'Babies & Kids',
                'slug' => 'babies-kids',
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
            ],
            [
                'name' => 'Groceries',
                'slug' => 'groceries',
            ],
            [
                'name' => 'Toys, Games & Collectibles',
                'slug' => 'toys-games-collectibles',
            ],
            [
                'name' => "Women's Bags",
                'slug' => 'womens-bags',
            ],
            [
                'name' => 'Women Accessories',
                'slug' => 'women-accessories',
            ],
            [
                'name' => "Women's Apparel",
                'slug' => 'womens-apparel',
            ],
            [
                'name' => 'Health & Personal Care',
                'slug' => 'health-personal-care',
            ],
            [
                'name' => 'Makeup & Fragrances',
                'slug' => 'makeup-fragrances',
            ],
            [
                'name' => 'Home Appliances',
                'slug' => 'home-appliances',
            ],
            [
                'name' => 'Laptops & Computers',
                'slug' => 'laptops-computers',
            ],
            [
                'name' => 'Cameras',
                'slug' => 'cameras',
            ],
            [
                'name' => 'Sports & Travel',
                'slug' => 'sports-travel',
            ],
            [
                'name' => "Men's Bags & Accessories",
                'slug' => 'mens-bags-accessories',
            ],
            [
                'name' => "Men's Shoes",
                'slug' => 'mens-shoes',
            ],
            [
                'name' => 'Motors',
                'slug' => 'motors',
            ],
            [
                'name' => "Women's Shoes",
                'slug' => 'womens-shoes',
            ],
            [
                'name' => 'Pet Care',
                'slug' => 'pet-care',
            ],
            [
                'name' => 'Audio',
                'slug' => 'audio',
            ],
            [
                'name' => 'Hobbies & Stationery',
                'slug' => 'hobbies-stationery',
            ],
            [
                'name' => 'Gaming',
                'slug' => 'gaming',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                [
                    'slug' => $category['slug'],
                ],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}