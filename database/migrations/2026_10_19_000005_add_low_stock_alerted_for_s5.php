<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'low_stock_alerted')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('low_stock_alerted')->default(false)->after('low_stock_threshold');
            });
        }

        if (Schema::hasTable('product_variants') && ! Schema::hasColumn('product_variants', 'low_stock_alerted')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->boolean('low_stock_alerted')->default(false)->after('stock');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'low_stock_alerted')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('low_stock_alerted');
            });
        }

        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'low_stock_alerted')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('low_stock_alerted');
            });
        }
    }
};

