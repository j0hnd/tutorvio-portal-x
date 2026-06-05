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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('type');
            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('course_program_id')->nullable()->constrained('course_programs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_message_at')->nullable();
            $table->foreignId('last_message_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('last_message_preview')->nullable();
            $table->json('last_message_metadata')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('course_program_id');
            $table->index('created_by');
            $table->index('last_message_at');
            $table->index('last_message_by');
            $table->index(['type', 'status']);
            $table->index(['student_id', 'teacher_id']);
            $table->index(['course_program_id', 'status']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participant_role')->nullable();
            $table->string('participant_role_snapshot')->nullable();
            $table->json('participant_roles_snapshot')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('muted_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['conversation_id', 'user_id'], 'conversation_user_unique');
            $table->index('conversation_id');
            $table->index('user_id');
            $table->index('participant_role');
            $table->index('participant_role_snapshot');
            $table->index(['user_id', 'last_read_at']);
            $table->index(['user_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
