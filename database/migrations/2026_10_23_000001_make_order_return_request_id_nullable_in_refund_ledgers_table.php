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
        if (Schema::hasTable('refund_ledgers')) {
            Schema::table('refund_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('refund_ledgers', 'order_return_request_id')) {
                    $table->unsignedBigInteger('order_return_request_id')->nullable()->change();
                }
                if (Schema::hasColumn('refund_ledgers', 'order_item_id')) {
                    $table->unsignedBigInteger('order_item_id')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('refund_ledgers')) {
            Schema::table('refund_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('refund_ledgers', 'order_return_request_id')) {
                    $table->unsignedBigInteger('order_return_request_id')->nullable(false)->change();
                }
                if (Schema::hasColumn('refund_ledgers', 'order_item_id')) {
                    $table->unsignedBigInteger('order_item_id')->nullable(false)->change();
                }
            });
        }
    }
};

