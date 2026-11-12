<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // gcash, maya, card
            $table->string('label')->nullable();
            $table->string('brand', 30)->nullable();
            $table->string('holder_name')->nullable();
            // Only masked/last digits are stored. Never full card numbers or CVV.
            $table->string('last_four', 4);
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_method', 30)->nullable()->after('shipping_address');
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('shipping_method');
            $table->string('payment_status', 20)->default('unpaid')->after('payment_method');
            $table->unsignedBigInteger('payment_method_id')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_method',
                'shipping_fee',
                'payment_status',
                'payment_method_id',
            ]);
        });

        Schema::dropIfExists('payment_methods');
    }
};

