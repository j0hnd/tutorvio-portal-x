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
        Schema::create('conversation_message_pins', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('pinned_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('conversation_message_id');
            $table->index(['conversation_id', 'pinned_at']);
            $table->index('pinned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_message_pins');
    }
};
