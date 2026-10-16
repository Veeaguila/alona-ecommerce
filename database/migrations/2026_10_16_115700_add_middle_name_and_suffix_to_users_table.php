<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name', 50)->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('users', 'suffix')) {
                $table->string('suffix', 20)->nullable()->after('middle_initial');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_filter(
                ['middle_name', 'suffix'],
                fn (string $column) => Schema::hasColumn('users', $column)
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

