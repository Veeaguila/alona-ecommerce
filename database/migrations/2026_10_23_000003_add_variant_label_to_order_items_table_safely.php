<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'variant_label')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('variant_label')->nullable()->after('product_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'variant_label')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('variant_label');
            });
        }
    }
};

