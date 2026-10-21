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
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'per_buyer_limit')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('per_buyer_limit')->nullable()->default(null)->after('stock');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'per_buyer_limit')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('per_buyer_limit');
            });
        }
    }
};

