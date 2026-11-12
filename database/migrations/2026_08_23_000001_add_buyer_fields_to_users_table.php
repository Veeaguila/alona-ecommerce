<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
            $table->string('first_name')->nullable()->after('last_name');
            $table->string('middle_initial', 1)->nullable()->after('first_name');
            $table->string('sex')->nullable()->after('middle_initial');
            $table->string('contact_no')->nullable()->after('email');
            $table->date('birthday')->nullable()->after('contact_no');
            $table->unsignedTinyInteger('age')->nullable()->after('birthday');
            $table->text('address')->nullable()->after('age');
            $table->string('id_path')->nullable()->after('address');
            $table->string('usertype')->default('buyer')->after('id_path');
            $table->string('status')->default('approved')->after('usertype');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_name', 'first_name', 'middle_initial', 'sex', 'contact_no',
                'birthday', 'age', 'address', 'id_path', 'usertype', 'status',
            ]);
        });
    }
};
