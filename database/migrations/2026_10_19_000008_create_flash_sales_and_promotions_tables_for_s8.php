<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flash_sales')) {
            Schema::create('flash_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('flash_price', 10, 2);
                $table->unsignedInteger('flash_quantity');
                $table->unsignedInteger('sold_quantity')->default(0);
                $table->string('slot', 40)->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'starts_at', 'ends_at']);
                $table->index(['seller_id', 'starts_at']);
            });
        }

        if (! Schema::hasTable('seller_promotions')) {
            Schema::create('seller_promotions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 120)->nullable();
                $table->string('type', 30)->default('bundle'); // 'bundle' | 'buy_x_get_y'
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->json('product_ids')->nullable();
                $table->unsignedInteger('min_quantity')->nullable();
                $table->string('discount_type', 20)->default('percentage'); // 'percentage' | 'fixed'
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->foreignId('buy_product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->unsignedInteger('buy_quantity')->nullable();
                $table->foreignId('get_product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->unsignedInteger('get_quantity')->default(1);
                $table->decimal('get_discount_percentage', 5, 2)->default(100.00);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['seller_id', 'type', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_promotions');
        Schema::dropIfExists('flash_sales');
    }
};

