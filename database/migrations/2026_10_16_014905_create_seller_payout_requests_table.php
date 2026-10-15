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
        if (!Schema::hasTable('seller_payout_requests')) {
            Schema::create('seller_payout_requests', function (Blueprint $table) {
                $table->id();
                $table->string('payout_reference', 50)->unique();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('payout_method', 50)->default('gcash');
                $table->string('account_name', 255);
                $table->string('account_number', 50);
                $table->string('bank_name', 100)->nullable();
                $table->string('status', 30)->default('pending'); // pending, processing, approved, completed, rejected
                $table->text('admin_notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['seller_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_payout_requests');
    }
};

