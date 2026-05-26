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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('type');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('sender_id');
            $table->index('scheduled_at');
            $table->index('published_at');
            $table->index('is_archived');
            $table->index('archived_by');
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel')->default('in_portal');
            $table->string('delivery_status')->default('pending');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id', 'channel'], 'notification_user_channel_unique');
            $table->index('notification_id');
            $table->index('user_id');
            $table->index('channel');
            $table->index('delivery_status');
            $table->index('read_at');
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('admin_announcement');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('author_id');
            $table->index('scheduled_at');
            $table->index('published_at');
            $table->index('expires_at');
            $table->index('is_archived');
            $table->index('archived_by');
        });

        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('announcement_id');
            $table->index(['target_type', 'target_id']);
            $table->index('user_id');
            $table->index('role');
        });

        Schema::create('announcement_read_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id'], 'announcement_user_read_unique');
            $table->index('announcement_id');
            $table->index('user_id');
            $table->index('read_at');
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('thread_type')->default('student_teacher');
            $table->string('status')->default('active');
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_message_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('thread_type');
            $table->index('status');
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('created_by');
            $table->index('last_message_at');
            $table->index('is_archived');
            $table->index(['student_id', 'teacher_id']);
            $table->index(['thread_type', 'status']);
        });

        Schema::create('message_thread_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participant_role')->nullable();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('muted_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['message_thread_id', 'user_id'], 'message_thread_user_unique');
            $table->index('message_thread_id');
            $table->index('user_id');
            $table->index('participant_role');
            $table->index(['user_id', 'last_read_at']);
            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('message_type')->default('student_teacher_message');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('message_thread_id');
            $table->index('sender_id');
            $table->index('message_type');
            $table->index('sent_at');
            $table->index('archived_at');
            $table->index(['message_thread_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_thread_participants');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('announcement_read_states');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }
};
