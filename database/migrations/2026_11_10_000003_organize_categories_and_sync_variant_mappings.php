<?php

use Database\Seeders\CategorySeeder;
use Database\Seeders\VariantCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Organize categories and subcategories
        if (Schema::hasTable('categories')) {
            $categorySeeder = new CategorySeeder();
            $categorySeeder->run();
        }

        // 2. Synchronize master variant catalog and link variant types to categories
        if (Schema::hasTable('variant_types') && Schema::hasTable('category_variant_types')) {
            $variantSeeder = new VariantCatalogSeeder();
            $variantSeeder->run();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep category data safe
    }
};
