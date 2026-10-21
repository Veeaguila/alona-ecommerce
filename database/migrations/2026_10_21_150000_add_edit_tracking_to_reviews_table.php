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
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'edit_count')) {
                $table->unsignedInteger('edit_count')->default(0)->after('comment');
            }
            if (!Schema::hasColumn('reviews', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('edit_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'edited_at')) {
                $table->dropColumn('edited_at');
            }
            if (Schema::hasColumn('reviews', 'edit_count')) {
                $table->dropColumn('edit_count');
            }
        });
    }
};

