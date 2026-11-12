<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    $table->dropForeign(['buyer_id']);
                } catch (\Throwable $e) {}
                try {
                    $table->dropUnique(['buyer_id', 'seller_id']);
                } catch (\Throwable $e) {}
            }

            $table->unsignedBigInteger('buyer_id')->nullable()->change();

            if (DB::getDriverName() === 'mysql') {
                try {
                    $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
                } catch (\Throwable $e) {}
            }

            if (!Schema::hasColumn('conversations', 'type')) {
                $table->string('type', 30)->default('buyer_seller')->after('id');
            }
            if (!Schema::hasColumn('conversations', 'admin_id')) {
                $table->foreignId('admin_id')->nullable()->after('seller_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('conversations', 'subject')) {
                $table->string('subject', 200)->nullable()->after('admin_id');
            }
            if (!Schema::hasColumn('conversations', 'status')) {
                $table->string('status', 30)->default('open')->after('subject');
            }
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();

            if (!Schema::hasColumn('conversation_messages', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable()->after('body');
            }
            if (!Schema::hasColumn('conversation_messages', 'attachment_name')) {
                $table->string('attachment_name', 255)->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('conversation_messages', 'attachment_type')) {
                $table->string('attachment_type', 50)->nullable()->after('attachment_name');
            }
            if (!Schema::hasColumn('conversation_messages', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_type');
            }
            if (!Schema::hasColumn('conversation_messages', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('attachment_size')->constrained('orders')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_messages', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }
            if (Schema::hasColumn('conversation_messages', 'attachment_size')) {
                $table->dropColumn('attachment_size');
            }
            if (Schema::hasColumn('conversation_messages', 'attachment_type')) {
                $table->dropColumn('attachment_type');
            }
            if (Schema::hasColumn('conversation_messages', 'attachment_name')) {
                $table->dropColumn('attachment_name');
            }
            if (Schema::hasColumn('conversation_messages', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('conversations', 'subject')) {
                $table->dropColumn('subject');
            }
            if (Schema::hasColumn('conversations', 'admin_id')) {
                $table->dropForeign(['admin_id']);
                $table->dropColumn('admin_id');
            }
            if (Schema::hasColumn('conversations', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};

