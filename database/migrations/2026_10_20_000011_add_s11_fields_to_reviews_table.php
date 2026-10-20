<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('product_id')->constrained('order_items')->nullOnDelete();
            }
            if (!Schema::hasColumn('reviews', 'variant_id')) {
                $table->foreignId('variant_id')->nullable()->after('order_item_id')->constrained('product_variants')->nullOnDelete();
            }
            if (!Schema::hasColumn('reviews', 'variant_name')) {
                $table->string('variant_name', 120)->nullable()->after('variant_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'order_item_id')) {
                $table->dropForeign(['order_item_id']);
                $table->dropColumn('order_item_id');
            }
            if (Schema::hasColumn('reviews', 'variant_id')) {
                $table->dropForeign(['variant_id']);
                $table->dropColumn('variant_id');
            }
            if (Schema::hasColumn('reviews', 'variant_name')) {
                $table->dropColumn('variant_name');
            }
        });
    }
};

