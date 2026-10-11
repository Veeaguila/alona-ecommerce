<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sorting_centers')) {
            Schema::create('sorting_centers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('business_name');
                $table->string('permit_doc_url', 2048)->nullable();
                $table->string('approval_status', 20)->default('pending')->index();
                $table->timestamps();
                $table->string('id_doc_url', 2048)->nullable();
            });
        }

        if (!Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sorting_center_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();
                $table->unique(['sorting_center_id', 'name'], 'areas_center_name_unique');
            });
        }

        if (!Schema::hasTable('riders')) {
            Schema::create('riders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained();
                $table->foreignId('sorting_center_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('name');
                $table->string('contact', 30);
                $table->string('sex', 20)->nullable();
                $table->date('birthday')->nullable();
                $table->string('province', 100)->nullable();
                $table->string('municipality', 100)->nullable();
                $table->string('barangay', 100)->nullable();
                $table->string('street')->nullable();
                $table->string('house_number', 50)->nullable();
                $table->string('vehicle_type', 30);
                $table->string('plate_number', 20);
                $table->string('or_cr_doc_url', 2048)->nullable();
                $table->string('license_doc_url', 2048)->nullable();
                $table->string('approval_status', 20)->default('pending')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->index(['sorting_center_id', 'approval_status'], 'riders_center_approval_status_index');
            });
        }

        if (!Schema::hasTable('rider_areas')) {
            Schema::create('rider_areas', function (Blueprint $table): void {
                $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
                $table->foreignId('area_id')->constrained()->cascadeOnDelete();
                $table->primary(['rider_id', 'area_id']);
                $table->index('area_id');
            });
        }

        if (!Schema::hasTable('parcels')) {
            Schema::create('parcels', function (Blueprint $table): void {
                $table->id();
                $table->string('order_id', 50)->unique();
                $table->foreignId('order_item_id')->nullable()->unique()->constrained('order_items')->restrictOnDelete();
                $table->text('delivery_address')->nullable();
                $table->text('collection_address')->nullable();
                $table->foreignId('buyer_id')->constrained('users');
                $table->foreignId('seller_id')->constrained('users');
                $table->foreignId('sorting_center_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('status', 30)->default('PLACED')->index();
                $table->string('delivery_province', 100);
                $table->string('delivery_municipality', 100);
                $table->string('delivery_barangay', 100);
                $table->string('delivery_street')->nullable();
                $table->string('delivery_house_number', 50)->nullable();
                $table->foreignId('sorted_area_id')->nullable()->constrained('areas')->nullOnDelete();
                $table->foreignId('assigned_rider_id')->nullable()->constrained('riders')->nullOnDelete();
                $table->foreignId('pickup_rider_id')->nullable()->constrained('riders')->nullOnDelete();
                $table->timestamp('sorted_at')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
                $table->index(
                    ['delivery_province', 'delivery_municipality', 'delivery_barangay'],
                    'parcels_delivery_area_index'
                );
                $table->index(['sorting_center_id', 'status'], 'parcels_center_status_index');
                $table->index(['sorting_center_id', 'sorted_area_id'], 'parcels_center_area_index');
            });
        }

        if (!Schema::hasTable('pickup_requests')) {
            Schema::create('pickup_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
                $table->foreignId('seller_id')->constrained('users');
                $table->foreignId('sorting_center_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('requested_at')->useCurrent();
                $table->timestamps();
                $table->index(['sorting_center_id', 'status'], 'pickup_requests_center_status_index');
            });
        }

    }

    public function down(): void
    {
        // Sanctum tokens may predate this module and belong to other clients.
        foreach (['pickup_requests', 'parcels', 'rider_areas', 'riders', 'areas', 'sorting_centers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
