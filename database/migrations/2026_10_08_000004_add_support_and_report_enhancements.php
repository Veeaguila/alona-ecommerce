<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                if (! Schema::hasColumn('complaints', 'product_id')) {
                    $table->foreignId('product_id')->nullable()->after('order_id')->constrained('products')->nullOnDelete();
                }

                if (! Schema::hasColumn('complaints', 'type')) {
                    $table->string('type')->default('complaint')->after('subject');
                }

                if (! Schema::hasColumn('complaints', 'contact_email')) {
                    $table->string('contact_email')->nullable()->after('description');
                }
            });
        }

        if (Schema::hasTable('buyer_notifications')) {
            Schema::table('buyer_notifications', function (Blueprint $table) {
                if (! Schema::hasColumn('buyer_notifications', 'type')) {
                    $table->string('type')->default('general')->after('title');
                }

                if (! Schema::hasColumn('buyer_notifications', 'action_url')) {
                    $table->string('action_url')->nullable()->after('message');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                if (Schema::hasColumn('complaints', 'product_id')) {
                    $table->dropConstrainedForeignId('product_id');
                }
                if (Schema::hasColumn('complaints', 'type')) {
                    $table->dropColumn('type');
                }
                if (Schema::hasColumn('complaints', 'contact_email')) {
                    $table->dropColumn('contact_email');
                }
            });
        }

        if (Schema::hasTable('buyer_notifications')) {
            Schema::table('buyer_notifications', function (Blueprint $table) {
                if (Schema::hasColumn('buyer_notifications', 'type')) {
                    $table->dropColumn('type');
                }
                if (Schema::hasColumn('buyer_notifications', 'action_url')) {
                    $table->dropColumn('action_url');
                }
            });
        }
    }
};

