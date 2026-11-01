<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_hub_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sorting_center_id')->constrained()->restrictOnDelete();
            $table->foreignId('logistics_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['sorting_center_id', 'logistics_hub_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_hub_staff');
    }
};
