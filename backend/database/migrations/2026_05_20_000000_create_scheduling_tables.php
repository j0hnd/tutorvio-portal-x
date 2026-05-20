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
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('timezone');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('meeting_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'starts_at', 'ends_at']);
            $table->index(['student_id', 'starts_at', 'ends_at']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'day_of_week', 'is_active']);
        });

        Schema::create('teacher_unavailable_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone');
            $table->boolean('is_all_day')->default(false);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'starts_at', 'ends_at']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->string('timezone');
            $table->string('country_code', 2)->nullable();
            $table->boolean('repeats_annually')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['date', 'timezone', 'is_active']);
        });

        Schema::create('schedule_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default('email');
            $table->string('status')->default('pending');
            $table->timestampTz('scheduled_for');
            $table->timestampTz('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scheduled_for', 'status']);
            $table->index(['class_schedule_id', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_reminders');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('teacher_unavailable_dates');
        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('class_schedules');
    }
};
