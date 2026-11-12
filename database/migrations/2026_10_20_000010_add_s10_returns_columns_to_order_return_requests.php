<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_return_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_return_requests', 'response_deadline')) {
                $table->timestamp('response_deadline')->nullable()->after('evidence_images');
            }
            if (!Schema::hasColumn('order_return_requests', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('resolved_at');
            }
            if (!Schema::hasColumn('order_return_requests', 'auto_approved_at')) {
                $table->timestamp('auto_approved_at')->nullable()->after('escalated_at');
            }
            if (!Schema::hasColumn('order_return_requests', 'dispute_reason')) {
                $table->text('dispute_reason')->nullable()->after('rejection_reason');
            }
        });

        // Widen rejection_reason to text to support up to 300 characters safely across database engines
        if (Schema::hasColumn('order_return_requests', 'rejection_reason')) {
            try {
                Schema::table('order_return_requests', function (Blueprint $table) {
                    $table->text('rejection_reason')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Ignore if driver doesn't support changing column type directly
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_return_requests', function (Blueprint $table) {
            $cols = ['response_deadline', 'escalated_at', 'auto_approved_at', 'dispute_reason'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('order_return_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

