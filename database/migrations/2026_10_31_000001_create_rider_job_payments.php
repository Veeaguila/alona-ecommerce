<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_pay_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorting_center_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('pickup_cents')->nullable();
            $table->unsignedBigInteger('delivery_cents')->nullable();
            $table->unsignedBigInteger('transfer_cents')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('rider_pay_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->string('method', 12);
            $table->string('label', 120);
            $table->text('instructions'); // Encrypted; append-only Rider-provided instructions.
            $table->timestamp('created_at');
        });
        Schema::create('rider_pay_trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->foreignId('destination_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->string('transport_reference', 100);
            $table->json('manifest');
            $table->foreignId('dispatched_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('dispatched_at');
            $table->timestamp('completed_at')->nullable();
            $table->unique(['sorting_center_id', 'transport_reference'], 'rider_pay_trip_reference');
        });
        Schema::table('hub_transfers', function (Blueprint $table) {
            $table->foreignId('rider_pay_trip_id')->nullable()->constrained('rider_pay_trips')->restrictOnDelete();
        });
        Schema::create('rider_pay_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('request_hash', 64);
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_id')->constrained('rider_pay_destinations')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedInteger('items_count');
            $table->string('status', 12)->default('prepared');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('payment_key')->nullable()->unique();
            $table->string('reference', 120)->nullable();
            $table->string('reference_hash', 64)->nullable();
            $table->text('proof_path')->nullable();
            $table->string('proof_sha256', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['sorting_center_id', 'reference_hash'], 'rider_pay_payment_reference');
            $table->index(['rider_id', 'status']);
        });
        Schema::create('rider_pay_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('rider_pay_trips')->restrictOnDelete();
            $table->string('leg', 12);
            $table->string('work_key', 100)->index();
            $table->string('active_key', 100)->nullable()->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedInteger('rate_revision');
            $table->json('snapshot');
            $table->string('status', 12)->default('pending');
            $table->foreignId('batch_id')->nullable()->constrained('rider_pay_batches')->restrictOnDelete();
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['sorting_center_id', 'rider_id', 'status'], 'rider_pay_earning_scope');
        });
        Schema::create('rider_pay_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('rider_pay_batches')->restrictOnDelete();
            $table->foreignId('earning_id')->constrained('rider_pay_earnings')->restrictOnDelete();
            $table->unique(['batch_id', 'earning_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rider earnings/payment history requires a separately reviewed preserving rollback.');
    }
};
