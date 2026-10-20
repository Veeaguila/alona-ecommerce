<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_sales')) {
            Schema::create('product_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('type', 20)->default('percentage'); // 'percentage' or 'fixed'
                $table->decimal('value', 10, 2);
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'starts_at', 'ends_at']);
                $table->index(['seller_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales');
    }
};

