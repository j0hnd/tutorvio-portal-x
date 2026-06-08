<?php

use App\Models\ChatReminder;
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
        Schema::create('chat_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->string('type');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('dedupe_key');
            $table->string('status')->default(ChatReminder::STATUS_SENT);
            $table->json('recipient_user_ids')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('dedupe_key');
            $table->index('conversation_id');
            $table->index('conversation_message_id');
            $table->index('type');
            $table->index(['source_type', 'source_id']);
            $table->index(['conversation_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_reminders');
    }
};
