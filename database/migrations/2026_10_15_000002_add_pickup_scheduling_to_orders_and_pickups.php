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
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'scheduled_pickup_date')) {
                    $table->date('scheduled_pickup_date')->nullable()->after('courier_name');
                }
                if (!Schema::hasColumn('order_items', 'pickup_time_slot')) {
                    $table->string('pickup_time_slot', 50)->nullable()->after('scheduled_pickup_date');
                }
                if (!Schema::hasColumn('order_items', 'preferred_carrier')) {
                    $table->string('preferred_carrier', 100)->nullable()->after('pickup_time_slot');
                }
                if (!Schema::hasColumn('order_items', 'pickup_notes')) {
                    $table->text('pickup_notes')->nullable()->after('preferred_carrier');
                }
            });
        }

        if (Schema::hasTable('pickup_requests')) {
            Schema::table('pickup_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('pickup_requests', 'scheduled_date')) {
                    $table->date('scheduled_date')->nullable()->after('status');
                }
                if (!Schema::hasColumn('pickup_requests', 'time_slot')) {
                    $table->string('time_slot', 50)->nullable()->after('scheduled_date');
                }
                if (!Schema::hasColumn('pickup_requests', 'carrier_partner')) {
                    $table->string('carrier_partner', 100)->nullable()->after('time_slot');
                }
                if (!Schema::hasColumn('pickup_requests', 'notes')) {
                    $table->text('notes')->nullable()->after('carrier_partner');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $columns = ['scheduled_pickup_date', 'pickup_time_slot', 'preferred_carrier', 'pickup_notes'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('order_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pickup_requests')) {
            Schema::table('pickup_requests', function (Blueprint $table) {
                $columns = ['scheduled_date', 'time_slot', 'carrier_partner', 'notes'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('pickup_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

