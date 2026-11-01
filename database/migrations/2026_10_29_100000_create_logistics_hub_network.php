<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing sorting_centers remain the company/account boundary. No locations or
        // company relationships are inferred from legacy coverage or business names.
        Schema::create('logistics_hubs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->string('code', 24);
            $table->string('name', 100);
            $table->string('address', 500);
            $table->string('city_code', 10);
            $table->json('pickup_city_codes');
            $table->json('delivery_city_codes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['sorting_center_id', 'code']);
        });
        Schema::create('logistics_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->foreignId('destination_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->string('transport_mode', 12);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['origin_hub_id', 'destination_hub_id']);
        });
        Schema::table('riders', function (Blueprint $table): void {
            $table->foreignId('logistics_hub_id')->nullable()->constrained('logistics_hubs')->restrictOnDelete();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('hub_route_plan')->nullable();
        });
        Schema::create('parcel_hub_journeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parcel_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->foreignId('destination_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->foreignId('current_hub_id')->nullable()->constrained('logistics_hubs')->restrictOnDelete();
            $table->json('route_snapshot');
            $table->timestamp('origin_received_at')->nullable();
            $table->timestamp('destination_received_at')->nullable();
            $table->timestamps();
        });
        Schema::create('hub_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parcel_hub_journey_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('transport_reference', 100);
            $table->foreignId('dispatched_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dispatched_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_transfers');
        Schema::dropIfExists('parcel_hub_journeys');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('hub_route_plan'));
        Schema::table('riders', fn (Blueprint $table) => $table->dropConstrainedForeignId('logistics_hub_id'));
        Schema::dropIfExists('logistics_routes');
        Schema::dropIfExists('logistics_hubs');
    }
};
