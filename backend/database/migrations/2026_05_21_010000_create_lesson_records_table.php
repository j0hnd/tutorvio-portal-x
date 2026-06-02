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
        Schema::create('lesson_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('meeting_link')->nullable();
            $table->string('lesson_type');
            $table->string('lesson_status')->default('scheduled');
            $table->text('lesson_notes')->nullable();
            $table->text('homework_details')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'scheduled_date']);
            $table->index(['teacher_id', 'scheduled_date']);
            $table->index(['lesson_status', 'scheduled_date']);
            $table->index('lesson_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_records');
    }
};
