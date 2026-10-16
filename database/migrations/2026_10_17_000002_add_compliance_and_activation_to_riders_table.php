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
        Schema::table('riders', function (Blueprint $table): void {
            if (! Schema::hasColumn('riders', 'license_number')) {
                $table->string('license_number', 50)->nullable()->unique()->after('plate_number');
            }
            if (! Schema::hasColumn('riders', 'license_expiry')) {
                $table->date('license_expiry')->nullable()->after('license_number');
            }
            if (! Schema::hasColumn('riders', 'activation_token')) {
                $table->string('activation_token', 64)->nullable()->index()->after('license_expiry');
            }
            if (! Schema::hasColumn('riders', 'activation_token_expires_at')) {
                $table->timestamp('activation_token_expires_at')->nullable()->after('activation_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table): void {
            $table->dropColumn([
                'license_number',
                'license_expiry',
                'activation_token',
                'activation_token_expires_at',
            ]);
        });
    }
};

