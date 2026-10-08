<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow platform-wide vouchers (seller_id nullable)
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->change();
            if (!Schema::hasColumn('vouchers', 'is_platform')) {
                $table->boolean('is_platform')->default(false)->after('seller_id');
            }
        });

        // User Claimed Vouchers table (BUYER-25, BUYER-26, BUYER-27)
        Schema::create('user_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->string('status', 20)->default('claimed'); // claimed, used, expired
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expiring_alert_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'voucher_id']);
        });

        // Add media column to product reviews table (BUYER-31)
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'media')) {
                $table->json('media')->nullable()->after('comment');
            }
        });

        // Seller Reviews table (BUYER-30)
        Schema::create('seller_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'order_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_reviews');

        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'media')) {
                $table->dropColumn('media');
            }
        });

        Schema::dropIfExists('user_vouchers');

        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'is_platform')) {
                $table->dropColumn('is_platform');
            }
        });
    }
};

