<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('policy')->default('alona-90-10-70-20-10-v1');
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('product_cents');
            $table->unsignedBigInteger('shipping_cents');
            $table->unsignedBigInteger('buyer_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('shipping_discount_cents')->default(0);
            $table->bigInteger('unexplained_cents')->default(0);
            $table->json('snapshot');
            $table->timestamps();
        });
        Schema::create('payment_distribution_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained('payment_distributions')->restrictOnDelete();
            $table->string('line_key');
            $table->string('role');
            $table->foreignId('beneficiary_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('base_cents');
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['distribution_id', 'line_key']);
        });
        Schema::create('payment_distribution_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained('payment_distributions')->restrictOnDelete();
            $table->foreignId('line_id')->nullable()->constrained('payment_distribution_lines')->restrictOnDelete();
            $table->string('request_key', 120)->unique();
            $table->string('payload_hash', 64);
            $table->string('kind');
            $table->bigInteger('amount_cents')->default(0);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('external_reference')->nullable()->unique();
            $table->json('details');
            $table->timestamp('created_at');
            $table->index(['distribution_id', 'kind']);
            $table->index(['source_type', 'source_id']);
        });
        Schema::create('payment_distribution_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained('payment_distributions')->restrictOnDelete();
            $table->foreignId('line_id')->constrained('payment_distribution_lines')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained('users')->restrictOnDelete();
            $table->string('request_key', 120)->unique();
            $table->string('payload_hash', 64);
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('reserved');
            $table->text('destination');
            $table->string('transfer_reference')->nullable()->unique();
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_profitability_rules', function (Blueprint $table) {
            $table->id();
            $table->string('shipping_method')->unique();
            $table->boolean('available')->default(true);
            $table->boolean('block_shortfall')->default(false);
            $table->unsignedBigInteger('logistics_cost_cents')->default(0);
            $table->unsignedBigInteger('logistics_margin_cents')->default(0);
            $table->unsignedBigInteger('rider_cost_cents')->default(0);
            $table->unsignedBigInteger('rider_minimum_cents')->default(0);
            $table->json('blocked_zones')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        Schema::create('payment_product_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('cost_cents');
            $table->unsignedBigInteger('minimum_margin_cents')->default(0);
            $table->boolean('block_shortfall')->default(false);
            $table->timestamps();
        });
        Schema::create('payment_policy_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('kind');
            $table->json('details');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_policy_audits');
        Schema::dropIfExists('payment_product_costs');
        Schema::dropIfExists('payment_profitability_rules');
        Schema::dropIfExists('payment_distribution_payouts');
        Schema::dropIfExists('payment_distribution_events');
        Schema::dropIfExists('payment_distribution_lines');
        Schema::dropIfExists('payment_distributions');
    }
};
