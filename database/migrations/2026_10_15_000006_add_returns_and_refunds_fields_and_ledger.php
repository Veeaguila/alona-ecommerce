<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_return_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_return_requests', 'decision')) {
                $table->string('decision', 30)->nullable()->after('status');
            }
            if (!Schema::hasColumn('order_return_requests', 'rejection_reason')) {
                $table->string('rejection_reason', 100)->nullable()->after('decision');
            }
            if (!Schema::hasColumn('order_return_requests', 'return_instructions')) {
                $table->text('return_instructions')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('order_return_requests', 'return_warehouse_address')) {
                $table->text('return_warehouse_address')->nullable()->after('return_instructions');
            }
            if (!Schema::hasColumn('order_return_requests', 'return_carrier')) {
                $table->string('return_carrier', 100)->nullable()->after('return_warehouse_address');
            }
            if (!Schema::hasColumn('order_return_requests', 'return_tracking_number')) {
                $table->string('return_tracking_number', 100)->nullable()->after('return_carrier');
            }
            if (!Schema::hasColumn('order_return_requests', 'return_waybill_url')) {
                $table->string('return_waybill_url', 2048)->nullable()->after('return_tracking_number');
            }
            if (!Schema::hasColumn('order_return_requests', 'return_shipping_status')) {
                $table->string('return_shipping_status', 50)->nullable()->after('return_waybill_url');
            }
            if (!Schema::hasColumn('order_return_requests', 'return_shipping_history')) {
                $table->json('return_shipping_history')->nullable()->after('return_shipping_status');
            }
            if (!Schema::hasColumn('order_return_requests', 'buyer_shipped_at')) {
                $table->timestamp('buyer_shipped_at')->nullable()->after('return_shipping_history');
            }
            if (!Schema::hasColumn('order_return_requests', 'item_received_at')) {
                $table->timestamp('item_received_at')->nullable()->after('buyer_shipped_at');
            }
            if (!Schema::hasColumn('order_return_requests', 'inspection_status')) {
                $table->string('inspection_status', 50)->nullable()->after('item_received_at');
            }
            if (!Schema::hasColumn('order_return_requests', 'inspection_notes')) {
                $table->text('inspection_notes')->nullable()->after('inspection_status');
            }
            if (!Schema::hasColumn('order_return_requests', 'refunded_amount')) {
                $table->decimal('refunded_amount', 10, 2)->default(0)->after('inspection_notes');
            }
            if (!Schema::hasColumn('order_return_requests', 'refund_method')) {
                $table->string('refund_method', 50)->nullable()->after('refunded_amount');
            }
            if (!Schema::hasColumn('order_return_requests', 'restocked')) {
                $table->boolean('restocked')->default(false)->after('refund_method');
            }
        });

        if (!Schema::hasTable('refund_ledgers')) {
            Schema::create('refund_ledgers', function (Blueprint $table) {
                $table->id();
                $table->string('refund_reference', 50)->unique();
                $table->foreignId('order_return_request_id')->nullable()->constrained('order_return_requests')->nullOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('original_amount', 10, 2)->default(0);
                $table->decimal('refunded_amount', 10, 2)->default(0);
                $table->string('refund_type', 30)->default('return_and_refund');
                $table->string('refund_method', 50)->default('original_payment');
                $table->string('status', 30)->default('completed');
                $table->string('reason', 255);
                $table->text('notes')->nullable();
                $table->string('transaction_reference', 100)->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->useCurrent();
                $table->timestamps();

                $table->index(['seller_id', 'status']);
                $table->index(['order_id', 'order_item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_ledgers');

        Schema::table('order_return_requests', function (Blueprint $table) {
            $columns = [
                'decision',
                'rejection_reason',
                'return_instructions',
                'return_warehouse_address',
                'return_carrier',
                'return_tracking_number',
                'return_waybill_url',
                'return_shipping_status',
                'return_shipping_history',
                'buyer_shipped_at',
                'item_received_at',
                'inspection_status',
                'inspection_notes',
                'refunded_amount',
                'refund_method',
                'restocked',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('order_return_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

