<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additive only. Execution requires the user's explicit approval.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_key')->unique();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('sorting_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 80)->index();
            $table->json('data');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sorting_center_id', 'created_at']);
        });
        Schema::create('pickup_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('status', 30)->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('parcel_handovers', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_rider_id')->constrained('riders')->restrictOnDelete();
            $table->foreignId('to_rider_id')->constrained('riders')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('leg', 20);
            $table->string('status', 30)->default('pending');
            $table->text('reason');
            $table->json('parcel_ids');
            $table->timestamp('source_acknowledged_at')->nullable();
            $table->timestamp('target_acknowledged_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('parcel_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['parcel_id', 'attempt_number']);
        });
        Schema::create('parcel_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 40)->index();
            $table->text('reason');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('seller_received_at')->nullable();
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('parcel_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('previous_status', 30);
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->timestamps();
        });
        Schema::create('cod_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('evidence_path');
            $table->timestamp('collected_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sorting_center_id', 'collected_at']);
        });
        Schema::create('cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->decimal('submitted_amount', 12, 2);
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->string('evidence_path');
            $table->string('receipt_evidence_path')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cod_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cod_remittance_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('difference', 12, 2);
            $table->text('reason');
            $table->string('evidence_path');
            $table->string('status', 30)->default('pending');
            $table->text('resolution')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['cod_discrepancies', 'cod_remittances', 'cod_collections', 'parcel_incidents', 'parcel_returns',
            'parcel_delivery_attempts', 'parcel_handovers', 'pickup_offers', 'logistics_audit_events'] as $table) Schema::dropIfExists($table);
    }
};
