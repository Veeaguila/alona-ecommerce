<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'product_variant_id')) {
                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            $table->index(['user_id', 'product_id', 'product_variant_id'], 'cart_items_user_product_variant_index');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_user_product_variant_index');

            if (Schema::hasColumn('cart_items', 'product_variant_id')) {
                $table->dropConstrainedForeignId('product_variant_id');
            }
        });
    }
};