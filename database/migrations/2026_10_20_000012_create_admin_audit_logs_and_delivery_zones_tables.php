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
        if (! Schema::hasTable('admin_audit_logs')) {
            Schema::create('admin_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
                $table->string('action', 60);
                $table->string('target_type', 120)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->text('description');
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['admin_id', 'created_at']);
                $table->index('action');
            });
        }

        if (! Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('province', 100)->default('Bohol');
                $table->decimal('base_shipping_rate', 10, 2)->default(50.00);
                $table->decimal('per_kg_rate', 10, 2)->default(10.00);
                $table->string('estimated_days', 50)->default('1-3 Business Days');
                $table->boolean('is_active')->default(true);
                $table->foreignId('sorting_center_id')->nullable()->constrained('sorting_centers')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('admin_audit_logs');
    }
};

