<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Store profile (Manage Store)
            $table->string('store_name')->nullable()->after('usertype');
            $table->text('store_description')->nullable()->after('store_name');
            $table->string('store_logo_path')->nullable()->after('store_description');

            // Settings: shipping, payment, returns, notifications
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('store_logo_path');
            $table->unsignedSmallInteger('return_policy_days')->default(30)->after('shipping_fee');
            $table->string('bank_name')->nullable()->after('return_policy_days');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->boolean('notify_new_order')->default(true)->after('bank_account_number');
            $table->boolean('notify_messages')->default(true)->after('notify_new_order');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'store_name', 'store_description', 'store_logo_path',
                'shipping_fee', 'return_policy_days',
                'bank_name', 'bank_account_name', 'bank_account_number',
                'notify_new_order', 'notify_messages',
            ]);
        });
    }
};
