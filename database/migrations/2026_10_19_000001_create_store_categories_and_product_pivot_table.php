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
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('store_categories')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('slug', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['seller_id', 'parent_id']);
            $table->index(['seller_id', 'sort_order']);
        });

        Schema::create('product_store_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_category_id')->constrained('store_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'store_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_store_category');
        Schema::dropIfExists('store_categories');
    }
};

