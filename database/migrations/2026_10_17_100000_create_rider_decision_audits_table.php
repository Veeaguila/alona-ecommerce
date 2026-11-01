<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_decision_audits', function (Blueprint $table) {
            $table->id();
            // Snapshot identifiers deliberately have no cascading foreign keys:
            // account/profile deletion must not erase historical decisions.
            $table->unsignedBigInteger('sorting_center_id');
            $table->unsignedBigInteger('rider_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_name');
            $table->string('action', 20);
            $table->json('old_state');
            $table->json('new_state');
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->index(['sorting_center_id', 'rider_id', 'occurred_at', 'id'], 'rider_decision_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_decision_audits');
    }
};
