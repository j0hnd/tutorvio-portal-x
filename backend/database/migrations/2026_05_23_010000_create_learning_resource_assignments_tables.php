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
        Schema::create('learning_resource_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_resource_id')->constrained('learning_resources')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestamps();

            $table->unique(['learning_resource_id', 'student_id']);
            $table->index(['student_id', 'assigned_at']);
            $table->index('assigned_by');
        });

        Schema::create('learning_resource_lesson', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_resource_id')->constrained('learning_resources')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestamps();

            $table->unique(['learning_resource_id', 'lesson_id']);
            $table->index(['lesson_id', 'assigned_at']);
            $table->index('assigned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_resource_lesson');
        Schema::dropIfExists('learning_resource_student');
    }
};
