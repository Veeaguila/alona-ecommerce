<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'buyer_note')) {
                    $table->string('buyer_note', 500)->nullable()->after('shipping_address');
                }
                if (! Schema::hasColumn('orders', 'refund_status')) {
                    $table->string('refund_status', 40)->nullable()->after('payment_status');
                }
                if (! Schema::hasColumn('orders', 'refund_amount')) {
                    $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_status');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('seller_note');
                }
                if (! Schema::hasColumn('order_items', 'ready_for_pickup_at')) {
                    $table->timestamp('ready_for_pickup_at')->nullable()->after('packed_at');
                }
            });
        }

        if (Schema::hasTable('refund_ledgers') && Schema::hasColumn('refund_ledgers', 'order_return_request_id')) {
            Schema::table('refund_ledgers', function (Blueprint $table) {
                $table->unsignedBigInteger('order_return_request_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['buyer_note', 'refund_status', 'refund_amount'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['accepted_at', 'ready_for_pickup_at'] as $col) {
                    if (Schema::hasColumn('order_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
