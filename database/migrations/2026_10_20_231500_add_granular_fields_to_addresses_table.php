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
        Schema::table('addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('addresses', 'recipient_name')) {
                $table->string('recipient_name', 100)->nullable()->after('label');
            }

            if (! Schema::hasColumn('addresses', 'recipient_phone')) {
                $table->string('recipient_phone', 30)->nullable()->after('recipient_name');
            }

            if (! Schema::hasColumn('addresses', 'region')) {
                $table->string('region', 100)->nullable()->after('recipient_phone');
            }

            if (! Schema::hasColumn('addresses', 'province')) {
                $table->string('province', 100)->nullable()->after('region');
            }

            if (! Schema::hasColumn('addresses', 'municipality')) {
                $table->string('municipality', 100)->nullable()->after('province');
            }

            if (! Schema::hasColumn('addresses', 'barangay')) {
                $table->string('barangay', 100)->nullable()->after('municipality');
            }

            if (! Schema::hasColumn('addresses', 'street_address')) {
                $table->string('street_address', 255)->nullable()->after('barangay');
            }

            if (! Schema::hasColumn('addresses', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('street_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $cols = [
                'recipient_name',
                'recipient_phone',
                'region',
                'province',
                'municipality',
                'barangay',
                'street_address',
                'postal_code',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('addresses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

