<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Per-line-item fulfillment status. Orders can contain products from
            // multiple sellers (the cart isn't seller-scoped), so each seller
            // only ever acts on their own order_items, not the whole order.
            $table->string('status')->default('pending')->after('quantity');
            $table->string('courier_name')->nullable()->after('status');
            $table->string('tracking_number')->nullable()->after('courier_name');
            $table->text('seller_note')->nullable()->after('tracking_number');
            $table->timestamp('packed_at')->nullable()->after('seller_note');
            $table->timestamp('shipped_at')->nullable()->after('packed_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'courier_name', 'tracking_number', 'seller_note',
                'packed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
            ]);
        });
    }
};
