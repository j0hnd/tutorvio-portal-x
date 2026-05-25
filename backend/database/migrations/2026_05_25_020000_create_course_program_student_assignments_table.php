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
        Schema::create('course_program_student_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_program_id')->constrained('course_programs')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['course_program_id', 'student_id'], 'course_program_student_unique');
            $table->index('course_program_id');
            $table->index('student_id');
            $table->index('assigned_by');
            $table->index('status');
            $table->index(['student_id', 'status']);
            $table->index(['course_program_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_program_student_assignments');
    }
};
