<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to establish the centralized, database-driven product variant catalog,
     * category mappings, automatic product SKU, and enhanced variant combinations.
     */
    public function up(): void
    {
        // 1. Variant Types table
        if (! Schema::hasTable('variant_types')) {
            Schema::create('variant_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->string('display_type', 30)->default('pills'); // color_swatch, pills, select, button
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Variant Values table
        if (! Schema::hasTable('variant_values')) {
            Schema::create('variant_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('variant_type_id')->constrained('variant_types')->cascadeOnDelete();
                $table->string('value', 191);
                $table->string('label', 191);
                $table->json('meta')->nullable(); // e.g. {"hex": "#000000", "system": "EU"}
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['variant_type_id', 'value'], 'var_val_type_val_unique');
            });
        }

        // 3. Category Variant Mappings table
        if (! Schema::hasTable('category_variant_types')) {
            Schema::create('category_variant_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->foreignId('variant_type_id')->constrained('variant_types')->cascadeOnDelete();
                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['category_id', 'variant_type_id'], 'cat_var_type_unique');
            });
        }

        // 4. Automatic Product SKU column on products table
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('sku', 64)->nullable()->unique()->after('slug');
            });
        }

        // 5. Enhance product_variants table for multi-attribute combinations
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                if (! Schema::hasColumn('product_variants', 'variant_attributes')) {
                    $table->json('variant_attributes')->nullable()->after('size');
                }
                if (! Schema::hasColumn('product_variants', 'combination_key')) {
                    $table->string('combination_key', 191)->nullable()->after('variant_attributes')->index();
                }
                if (! Schema::hasColumn('product_variants', 'weight')) {
                    $table->decimal('weight', 8, 2)->nullable()->after('stock');
                }
                if (! Schema::hasColumn('product_variants', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('weight');
                }
            });
        }

        // 6. Backfill existing products with unique SKU format ALN-XXXXXX
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sku')) {
            $existingProducts = DB::table('products')->whereNull('sku')->orderBy('id')->get(['id']);
            foreach ($existingProducts as $p) {
                $sku = 'ALN-' . str_pad((string) $p->id, 6, '0', STR_PAD_LEFT);
                DB::table('products')->where('id', $p->id)->update(['sku' => $sku]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                foreach (['variant_attributes', 'combination_key', 'weight', 'is_active'] as $col) {
                    if (Schema::hasColumn('product_variants', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('sku');
            });
        }

        Schema::dropIfExists('category_variant_types');
        Schema::dropIfExists('variant_values');
        Schema::dropIfExists('variant_types');
    }
};
