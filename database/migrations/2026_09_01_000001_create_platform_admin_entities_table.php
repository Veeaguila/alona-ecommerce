<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('complaints')) {
            Schema::create('complaints', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('subject');
                $table->text('description');
                $table->json('evidence')->nullable();
                $table->string('status')->default('pending');
                $table->text('resolution')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('sale_amount', 12, 2)->default(0);
                $table->decimal('commission_rate', 5, 2)->default(10);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->decimal('seller_amount', 12, 2)->default(0);
                $table->string('status')->default('pending');
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('body');
                $table->string('status')->default('draft');
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('policies')) {
            Schema::create('policies', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->string('version')->default('1.0');
                $table->boolean('is_published')->default(false);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('policies');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('complaints');
    }
};
