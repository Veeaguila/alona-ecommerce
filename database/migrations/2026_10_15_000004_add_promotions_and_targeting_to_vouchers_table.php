<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('vouchers', 'max_discount')) {
                $table->decimal('max_discount', 10, 2)->nullable()->after('value');
            }
            if (!Schema::hasColumn('vouchers', 'per_customer_limit')) {
                $table->unsignedInteger('per_customer_limit')->nullable()->default(1)->after('usage_limit');
            }
            if (!Schema::hasColumn('vouchers', 'target_type')) {
                $table->string('target_type', 20)->default('all')->after('per_customer_limit'); // all, product, category
            }
            if (!Schema::hasColumn('vouchers', 'targeted_product_ids')) {
                $table->json('targeted_product_ids')->nullable()->after('target_type');
            }
            if (!Schema::hasColumn('vouchers', 'targeted_category_ids')) {
                $table->json('targeted_category_ids')->nullable()->after('targeted_product_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $columns = [];
            foreach (['max_discount', 'per_customer_limit', 'target_type', 'targeted_product_ids', 'targeted_category_ids'] as $col) {
                if (Schema::hasColumn('vouchers', $col)) {
                    $columns[] = $col;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

