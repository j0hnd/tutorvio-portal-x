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
        Schema::create('student_progress_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('skill_area')->nullable();
            $table->json('progress_summary_by_skill')->nullable();
            $table->string('speaking_confidence_rating')->nullable();
            $table->text('vocabulary_progress')->nullable();
            $table->text('grammar_development')->nullable();
            $table->text('pronunciation_progress')->nullable();
            $table->unsignedInteger('lesson_completion_count')->default(0);
            $table->text('teacher_comments')->nullable();
            $table->json('milestone_achievements')->nullable();
            $table->string('level_movement')->nullable();
            $table->json('goals_completed')->nullable();
            $table->json('goals_in_progress')->nullable();
            $table->string('progress_status')->default('in_progress');
            $table->timestampTz('recorded_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'recorded_at']);
            $table->index(['teacher_id', 'recorded_at']);
            $table->index(['student_id', 'skill_area', 'recorded_at'], 'spr_student_skill_recorded_idx');
            $table->index(['student_id', 'progress_status', 'recorded_at'], 'spr_student_status_recorded_idx');
            $table->index('skill_area');
            $table->index('progress_status');
            $table->index('level_movement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_progress_records');
    }
};
