<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations to organize categories into a hierarchical structure.
     */
    public function up(): void
    {
        // 1. Ensure Top-Level Parent Categories exist
        $electronics = Category::updateOrCreate(
            ['slug' => 'electronics'],
            [
                'name' => 'Electronics',
                'image_path' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85',
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        $mensFashion = Category::updateOrCreate(
            ['slug' => 'mens-fashion'],
            [
                'name' => "Men's Fashion",
                'image_path' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=85',
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        $womensFashion = Category::updateOrCreate(
            ['slug' => 'womens-fashion'],
            [
                'name' => "Women's Fashion",
                'image_path' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85',
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        $beauty = Category::updateOrCreate(
            ['slug' => 'beauty'],
            [
                'name' => 'Health & Beauty',
                'image_path' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85',
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        // 2. Link Subcategories to their respective parents
        Category::whereIn('slug', [
            'mobiles-gadgets',
            'mobiles-accessories',
            'laptops-computers',
            'cameras',
            'audio',
            'gaming',
            'home-entertainment',
        ])->update(['parent_id' => $electronics->id]);

        Category::whereIn('slug', [
            'mens-apparel',
            'mens-shoes',
            'mens-bags-accessories',
        ])->update(['parent_id' => $mensFashion->id]);

        Category::whereIn('slug', [
            'womens-apparel',
            'womens-shoes',
            'womens-bags',
            'women-accessories',
        ])->update(['parent_id' => $womensFashion->id]);

        Category::whereIn('slug', [
            'health-personal-care',
            'makeup-fragrances',
        ])->update(['parent_id' => $beauty->id]);

        $homeLiving = Category::where('slug', 'home-living')->first();
        if ($homeLiving) {
            Category::where('slug', 'home-appliances')->update(['parent_id' => $homeLiving->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Category::query()->update(['parent_id' => null]);
    }
};

