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
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lesson_record_id')->nullable()->constrained('lesson_records')->nullOnDelete();
            $table->text('lesson_objective')->nullable();
            $table->text('topics_covered')->nullable();
            $table->text('vocabulary_learned')->nullable();
            $table->text('grammar_focus')->nullable();
            $table->text('pronunciation_issues')->nullable();
            $table->text('student_speaking_confidence_observation')->nullable();
            $table->text('homework_assignment')->nullable();
            $table->text('recommendation_for_next_lesson')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestamps();

            $table->unique('lesson_id');
            $table->unique('lesson_record_id');
            $table->index(['student_id', 'created_at']);
            $table->index(['teacher_id', 'created_at']);
            $table->index(['author_id', 'created_at']);
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_notes');
    }
};
