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
        if (!Schema::hasTable('sorting_center_messages')) {
            Schema::create('sorting_center_messages', function (Blueprint $table): void {
                $table->id();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sorting_center_messages')) {
            $columns = Schema::getColumnListing('sorting_center_messages');
            sort($columns);
            $baselineColumns = ['created_at', 'id', 'updated_at'];

            if ($columns === $baselineColumns) {
                Schema::drop('sorting_center_messages');
            }
        }
    }
};
