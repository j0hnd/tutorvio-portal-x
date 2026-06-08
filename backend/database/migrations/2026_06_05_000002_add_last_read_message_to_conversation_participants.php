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
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->after('last_read_at')
                ->constrained('conversation_messages')
                ->nullOnDelete();

            $table->index(['user_id', 'last_read_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropForeign(['last_read_message_id']);
            $table->dropIndex(['user_id', 'last_read_message_id']);
            $table->dropColumn('last_read_message_id');
        });
    }
};
