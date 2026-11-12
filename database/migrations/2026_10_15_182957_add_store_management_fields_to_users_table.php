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
            // SELLER-62: Store banner & branding
            $table->string('store_banner_path')->nullable()->after('store_logo_path');
            $table->string('store_tagline')->nullable()->after('store_banner_path');

            // SELLER-63: Operating hours schedule & response time
            $table->json('operating_hours')->nullable()->after('store_tagline');
            $table->string('response_time')->default('within_day')->after('operating_hours');

            // SELLER-64: Store policies & terms
            $table->text('shipping_policy')->nullable()->after('response_time');
            $table->text('warranty_policy')->nullable()->after('shipping_policy');
            $table->text('return_policy')->nullable()->after('warranty_policy');

            // SELLER-65: Vacation mode
            $table->boolean('vacation_mode')->default(false)->after('return_policy');
            $table->text('vacation_message')->nullable()->after('vacation_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'store_banner_path',
                'store_tagline',
                'operating_hours',
                'response_time',
                'shipping_policy',
                'warranty_policy',
                'return_policy',
                'vacation_mode',
                'vacation_message',
            ]);
        });
    }
};
