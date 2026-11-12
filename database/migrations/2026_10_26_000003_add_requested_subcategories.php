<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations to add requested subcategories.
     */
    public function up(): void
    {
        // 1. ELECTRONICS
        $electronics = Category::where('slug', 'electronics')->first();
        if ($electronics) {
            $electronicsSubs = [
                [
                    'name' => 'Smartwatches',
                    'slug' => 'smartwatches',
                    'image_path' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85',
                ],
                [
                    'name' => 'Drones',
                    'slug' => 'drones',
                    'image_path' => 'https://images.unsplash.com/photo-1508614589041-895b88991e3e?auto=format&fit=crop&w=900&q=85',
                ],
                [
                    'name' => 'PC Components',
                    'slug' => 'pc-components',
                    'image_path' => 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=900&q=85',
                ],
            ];

            foreach ($electronicsSubs as $sub) {
                Category::updateOrCreate(
                    ['slug' => $sub['slug']],
                    [
                        'parent_id' => $electronics->id,
                        'name' => $sub['name'],
                        'image_path' => $sub['image_path'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // 2. SNACKS
        $snacks = Category::where('slug', 'snacks')->first();
        if (! $snacks) {
            $snacks = Category::create([
                'name' => 'Snacks',
                'slug' => 'snacks',
                'image_path' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=85',
                'is_active' => true,
                'parent_id' => null,
            ]);
        }

        $snacksSubs = [
            [
                'name' => 'Fresh Produce',
                'slug' => 'fresh-produce',
                'image_path' => 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Beverages',
                'slug' => 'beverages',
                'image_path' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?auto=format&fit=crop&w=900&q=85',
            ],
            [
                'name' => 'Canned Goods',
                'slug' => 'canned-goods',
                'image_path' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&w=900&q=85',
            ],
        ];

        foreach ($snacksSubs as $sub) {
            Category::updateOrCreate(
                ['slug' => $sub['slug']],
                [
                    'parent_id' => $snacks->id,
                    'name' => $sub['name'],
                    'image_path' => $sub['image_path'],
                    'is_active' => true,
                ]
            );
        }

        // 3. HOME & LIVING
        $homeLiving = Category::where('slug', 'home-living')->first();
        if ($homeLiving) {
            $homeSubs = [
                [
                    'name' => 'Kitchen & Dining',
                    'slug' => 'kitchen-dining',
                    'image_path' => 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&w=900&q=85',
                ],
                [
                    'name' => 'Bedding',
                    'slug' => 'bedding',
                    'image_path' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=900&q=85',
                ],
                [
                    'name' => 'Furniture',
                    'slug' => 'furniture',
                    'image_path' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85',
                ],
            ];

            foreach ($homeSubs as $sub) {
                Category::updateOrCreate(
                    ['slug' => $sub['slug']],
                    [
                        'parent_id' => $homeLiving->id,
                        'name' => $sub['name'],
                        'image_path' => $sub['image_path'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // 4. SPORTS & TRAVEL
        $sportsTravel = Category::where('slug', 'sports-travel')->first();
        if ($sportsTravel) {
            $sportsSubs = [
                [
                    'name' => 'Camping & Hiking',
                    'slug' => 'camping-hiking',
                    'image_path' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?auto=format&fit=crop&w=900&q=85',
                ],
                [
                    'name' => 'Fitness Equipment',
                    'slug' => 'fitness-equipment',
                    'image_path' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?auto=format&fit=crop&w=900&q=85',
                ],
            ];

            foreach ($sportsSubs as $sub) {
                Category::updateOrCreate(
                    ['slug' => $sub['slug']],
                    [
                        'parent_id' => $sportsTravel->id,
                        'name' => $sub['name'],
                        'image_path' => $sub['image_path'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $slugs = [
            'smartwatches',
            'drones',
            'pc-components',
            'fresh-produce',
            'beverages',
            'canned-goods',
            'kitchen-dining',
            'bedding',
            'furniture',
            'camping-hiking',
            'fitness-equipment',
        ];

        Category::whereIn('slug', $slugs)->delete();
    }
};

