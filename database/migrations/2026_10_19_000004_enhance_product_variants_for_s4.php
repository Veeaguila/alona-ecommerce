<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'price')) {
                $table->decimal('price', 12, 2)->nullable()->after('size');
            }
            if (! Schema::hasColumn('product_variants', 'sku')) {
                $table->string('sku', 100)->nullable()->after('stock')->index();
            }
            if (! Schema::hasColumn('product_variants', 'image_path')) {
                $table->string('image_path')->nullable()->after('sku');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'variation_types')) {
                $table->json('variation_types')->nullable()->after('condition');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'variant_label')) {
                $table->string('variant_label')->nullable()->after('product_variant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            foreach (['price', 'sku', 'image_path'] as $col) {
                if (Schema::hasColumn('product_variants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'variation_types')) {
                $table->dropColumn('variation_types');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'variant_label')) {
                $table->dropColumn('variant_label');
            }
        });
    }
};

