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
            if (! Schema::hasColumn('users', 'pending_email')) {
                $table->string('pending_email')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'pending_email_otp')) {
                $table->string('pending_email_otp', 10)->nullable()->after('pending_email');
            }

            if (! Schema::hasColumn('users', 'pending_email_otp_expires_at')) {
                $table->timestamp('pending_email_otp_expires_at')->nullable()->after('pending_email_otp');
            }

            if (! Schema::hasColumn('users', 'pending_phone')) {
                $table->string('pending_phone', 30)->nullable()->after('contact_no');
            }

            if (! Schema::hasColumn('users', 'pending_phone_otp')) {
                $table->string('pending_phone_otp', 10)->nullable()->after('pending_phone');
            }

            if (! Schema::hasColumn('users', 'pending_phone_otp_expires_at')) {
                $table->timestamp('pending_phone_otp_expires_at')->nullable()->after('pending_phone_otp');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'pending_email',
                'pending_email_otp',
                'pending_email_otp_expires_at',
                'pending_phone',
                'pending_phone_otp',
                'pending_phone_otp_expires_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

