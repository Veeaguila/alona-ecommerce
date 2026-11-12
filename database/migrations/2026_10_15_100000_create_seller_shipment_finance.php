<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_rider_id')->nullable()->constrained('riders')->restrictOnDelete();
            $table->foreignId('sorting_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'seller_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            // Existing orders remain buyer-funded. Only checkout opts new orders in.
            $table->string('delivery_fee_payer', 10)->default('buyer');
            $table->decimal('shipping_fee_before_discount', 12, 2)->nullable();
            $table->decimal('shipping_discount', 12, 2)->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('seller_shipment_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('seller_delivery_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_shipment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->decimal('logistics_amount', 12, 2);
            $table->decimal('rider_amount', 12, 2);
            $table->unsignedSmallInteger('logistics_basis_points');
            $table->string('pricing_rule', 50);
            $table->timestamp('recognized_at');
            $table->string('collection_status', 15)->default('uncollected');
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('manual_reference', 150)->nullable();
            $table->uuid('settlement_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['seller_id', 'collection_status', 'recognized_at'], 'seller_charge_reporting');
        });
        Schema::create('rider_delivery_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_delivery_charge_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('earned_at');
            $table->string('payout_status', 10)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('manual_reference', 150)->nullable();
            $table->uuid('settlement_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['rider_id', 'payout_status', 'earned_at'], 'rider_earning_reporting');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_delivery_earnings');
        Schema::dropIfExists('seller_delivery_charges');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('seller_shipment_id'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['delivery_fee_payer', 'shipping_fee_before_discount', 'shipping_discount']));
        Schema::dropIfExists('seller_shipments');
    }
};
