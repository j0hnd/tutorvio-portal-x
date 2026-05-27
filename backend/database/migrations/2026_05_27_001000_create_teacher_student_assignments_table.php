<?php

use App\Models\TeacherStudentAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_student_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->string('status')->default(TeacherStudentAssignment::STATUS_ACTIVE);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('active_student_id')->nullable();
            $table->timestamps();

            $table->unique('active_student_id');
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('assigned_by');
            $table->index('status');
            $table->index(['student_id', 'status']);
            $table->index(['teacher_id', 'status']);
            $table->index(['student_id', 'teacher_id']);
        });

        DB::table('student_profiles')
            ->whereNotNull('assigned_teacher_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $profile) {
                DB::table('teacher_student_assignments')->insert([
                    'student_id' => $profile->user_id,
                    'teacher_id' => $profile->assigned_teacher_id,
                    'assigned_by' => null,
                    'assigned_at' => now(),
                    'status' => TeacherStudentAssignment::STATUS_ACTIVE,
                    'active_student_id' => $profile->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_student_assignments');
    }
};
