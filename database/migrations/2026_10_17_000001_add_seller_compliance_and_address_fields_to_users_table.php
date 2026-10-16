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
        Schema::table('users', function (Blueprint $table) {
            $table->string('seller_type', 50)->nullable()->after('usertype');
            $table->string('tin', 20)->nullable()->unique()->after('seller_type');
            $table->string('postal_code', 10)->nullable()->after('street_address');
            $table->string('dti_certificate_path')->nullable()->after('business_permit_path');
            $table->string('sec_certificate_path')->nullable()->after('dti_certificate_path');
            $table->string('bir_2303_path')->nullable()->after('sec_certificate_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tin']);
            $table->dropColumn([
                'seller_type',
                'tin',
                'postal_code',
                'dti_certificate_path',
                'sec_certificate_path',
                'bir_2303_path',
            ]);
        });
    }
};

