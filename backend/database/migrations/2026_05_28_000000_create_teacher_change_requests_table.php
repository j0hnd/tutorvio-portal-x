<?php

use App\Models\TeacherChangeRequest;
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
        Schema::create('teacher_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('current_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('requested_reason');
            $table->text('preferred_schedule_notes')->nullable();
            $table->string('status')->default(TeacherChangeRequest::STATUS_PENDING);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('current_teacher_id');
            $table->index('approved_teacher_id');
            $table->index('reviewed_by');
            $table->index('status');
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_change_requests');
    }
};
