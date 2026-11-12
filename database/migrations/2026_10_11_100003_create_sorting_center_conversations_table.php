<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorting_center_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sorting_center_id');
            $table->foreign('sorting_center_id', 'sc_conversations_center_fk')
                ->references('id')->on('sorting_centers')->cascadeOnDelete();
            $table->foreignId('created_by');
            $table->foreign('created_by', 'sc_conversations_creator_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['sorting_center_id', 'updated_at'], 'sc_conversations_center_updated_index');
        });

        Schema::create('sorting_center_conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id');
            $table->foreign('conversation_id', 'sc_conv_participants_conversation_fk')
                ->references('id')->on('sorting_center_conversations')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->foreign('user_id', 'sc_conv_participants_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id'], 'sc_conv_participants_unique');
            $table->index(['user_id', 'conversation_id'], 'sc_conv_participant_user_index');
        });

        Schema::table('sorting_center_messages', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->nullable()->after('id');
            $table->foreign('conversation_id', 'sc_messages_conversation_fk')
                ->references('id')->on('sorting_center_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->after('conversation_id');
            $table->foreign('sender_id', 'sc_messages_sender_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->after('sender_id');
            $table->foreign('recipient_id', 'sc_messages_recipient_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->text('body')->nullable()->after('recipient_id');
            $table->timestamp('read_at')->nullable()->after('body');
            $table->index(['conversation_id', 'created_at'], 'sc_messages_conversation_created_index');
            $table->index(['recipient_id', 'read_at'], 'sc_messages_recipient_read_index');
        });
    }

    public function down(): void
    {
        Schema::table('sorting_center_messages', function (Blueprint $table): void {
            $table->dropIndex('sc_messages_conversation_created_index');
            $table->dropIndex('sc_messages_recipient_read_index');
            $table->dropForeign('sc_messages_conversation_fk');
            $table->dropForeign('sc_messages_sender_fk');
            $table->dropForeign('sc_messages_recipient_fk');
            $table->dropColumn(['conversation_id', 'sender_id', 'recipient_id']);
            $table->dropColumn(['body', 'read_at']);
        });

        Schema::dropIfExists('sorting_center_conversation_participants');
        Schema::dropIfExists('sorting_center_conversations');
    }
};
