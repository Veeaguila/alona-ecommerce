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
        // 1. Add AI Verification fields to products table
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'ai_verification_status')) {
                $table->string('ai_verification_status', 30)->default('pending')->after('status');
                $table->index('ai_verification_status');
            }

            if (! Schema::hasColumn('products', 'ai_verification_explanation')) {
                $table->text('ai_verification_explanation')->nullable()->after('ai_verification_status');
            }

            if (! Schema::hasColumn('products', 'ai_confidence_score')) {
                $table->decimal('ai_confidence_score', 5, 2)->nullable()->after('ai_verification_explanation');
            }

            if (! Schema::hasColumn('products', 'ai_verified_at')) {
                $table->timestamp('ai_verified_at')->nullable()->after('ai_confidence_score');
            }

            if (! Schema::hasColumn('products', 'approval_method')) {
                $table->string('approval_method', 20)->nullable()->default('manual')->after('ai_verified_at');
                $table->index('approval_method');
            }

            if (! Schema::hasColumn('products', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_method');
            }

            if (! Schema::hasColumn('products', 'ai_raw_response')) {
                $table->json('ai_raw_response')->nullable()->after('rejection_reason');
            }
        });

        // 2. Make admin_id nullable on product_compliance_reviews for automated system compliance logs
        if (Schema::hasTable('product_compliance_reviews')) {
            Schema::table('product_compliance_reviews', function (Blueprint $table) {
                // Drop existing foreign key and column modification if needed
                // In MySQL/SQLite we make it nullable safely
                $table->unsignedBigInteger('admin_id')->nullable()->change();
            });
        }

        // 3. Create admin_notifications table
        if (! Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
                $table->string('type', 50)->default('ai_product_review_needed');
                $table->string('title', 255);
                $table->text('message');
                $table->json('data')->nullable();
                $table->string('action_url', 500)->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['admin_id', 'read_at']);
                $table->index(['product_id', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');

        Schema::table('products', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'ai_verification_status',
                'ai_verification_explanation',
                'ai_confidence_score',
                'ai_verified_at',
                'approval_method',
                'rejection_reason',
                'ai_raw_response',
            ], fn ($col) => Schema::hasColumn('products', $col));

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

