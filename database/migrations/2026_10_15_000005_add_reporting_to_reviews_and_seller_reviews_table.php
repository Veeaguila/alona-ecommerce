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
        // Add reporting fields to product reviews table (SELLER-32)
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'is_reported')) {
                $table->boolean('is_reported')->default(false)->after('seller_replied_at');
            }
            if (!Schema::hasColumn('reviews', 'report_reason')) {
                $table->string('report_reason', 100)->nullable()->after('is_reported');
            }
            if (!Schema::hasColumn('reviews', 'report_notes')) {
                $table->text('report_notes')->nullable()->after('report_reason');
            }
            if (!Schema::hasColumn('reviews', 'report_status')) {
                $table->string('report_status', 30)->default('none')->after('report_notes');
            }
            if (!Schema::hasColumn('reviews', 'reported_at')) {
                $table->timestamp('reported_at')->nullable()->after('report_status');
            }
        });

        // Add reporting and media fields to seller reviews table (SELLER-32, SELLER-33, SELLER-34)
        Schema::table('seller_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('seller_reviews', 'media')) {
                $table->json('media')->nullable()->after('comment');
            }
            if (!Schema::hasColumn('seller_reviews', 'is_reported')) {
                $table->boolean('is_reported')->default(false)->after('seller_replied_at');
            }
            if (!Schema::hasColumn('seller_reviews', 'report_reason')) {
                $table->string('report_reason', 100)->nullable()->after('is_reported');
            }
            if (!Schema::hasColumn('seller_reviews', 'report_notes')) {
                $table->text('report_notes')->nullable()->after('report_reason');
            }
            if (!Schema::hasColumn('seller_reviews', 'report_status')) {
                $table->string('report_status', 30)->default('none')->after('report_notes');
            }
            if (!Schema::hasColumn('seller_reviews', 'reported_at')) {
                $table->timestamp('reported_at')->nullable()->after('report_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'is_reported',
                'report_reason',
                'report_notes',
                'report_status',
                'reported_at',
            ]);
        });

        Schema::table('seller_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'media',
                'is_reported',
                'report_reason',
                'report_notes',
                'report_status',
                'reported_at',
            ]);
        });
    }
};

