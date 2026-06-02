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
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('assigned');
            $table->text('teacher_feedback')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->json('attachment_links')->nullable();
            $table->timestamps();

            $table->index('lesson_id');
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('status');
            $table->index('due_date');
            $table->index(['student_id', 'status', 'due_date']);
            $table->index(['teacher_id', 'status', 'due_date']);
            $table->index(['lesson_id', 'status']);
        });

        Schema::create('homework_learning_resource', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('learning_resource_id')->constrained('learning_resources')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_id', 'learning_resource_id'], 'hw_lr_homework_resource_unique');
            $table->index(['learning_resource_id', 'assigned_at'], 'hw_lr_resource_assigned_idx');
            $table->index('assigned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homework_learning_resource');
        Schema::dropIfExists('homeworks');
    }
};
