<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'display_mode')) {
                $table->string('display_mode', 20)->default('claimable')->after('target_type'); // claimable | code_only
            }
            if (! Schema::hasColumn('vouchers', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $cols = [];
            foreach (['display_mode', 'ended_at'] as $col) {
                if (Schema::hasColumn('vouchers', $col)) {
                    $cols[] = $col;
                }
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

