<?php

use App\Models\IssueReport;
use App\Models\ScheduleChangeRequest;
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
        Schema::create('operational_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience')->default('all');
            $table->string('priority')->default('normal');
            $table->string('status')->default('draft');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('audience');
            $table->index('priority');
            $table->index('status');
            $table->index('published_by');
            $table->index('starts_at');
            $table->index('published_at');
            $table->index(['status', 'starts_at']);
        });

        Schema::create('portal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category')->default('general');
            $table->json('value')->nullable();
            $table->string('value_type')->default('string');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
            $table->index('is_public');
            $table->index('updated_by');
        });

        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('template_type');
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->json('schema');
            $table->text('instructions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('template_type');
            $table->index('status');
            $table->index('created_by');
            $table->index(['template_type', 'status']);
        });

        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('class_schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->string('record_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->date('recorded_on')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'recorded_on']);
            $table->index(['teacher_id', 'recorded_on']);
            $table->index('lesson_id');
            $table->index('class_schedule_id');
            $table->index('record_type');
            $table->index('status');
            $table->index('recorded_by');
            $table->index('approved_by');
            $table->index(['student_id', 'record_type', 'recorded_on'], 'academic_student_type_recorded_idx');
        });

        Schema::create('issue_reports', function (Blueprint $table) {
            $table->id();
            $table->string('issue_type');
            $table->string('status')->default(IssueReport::STATUS_OPEN);
            $table->string('priority')->default(IssueReport::PRIORITY_NORMAL);
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('class_schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('related_student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('resolution_notes')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('issue_type');
            $table->index('priority');
            $table->index('reporter_id');
            $table->index('assigned_to_id');
            $table->index('related_student_id');
            $table->index('related_teacher_id');
            $table->index('target_user_id');
            $table->index('lesson_id');
            $table->index('class_schedule_id');
            $table->index('created_at');
            $table->index(['status', 'issue_type']);
            $table->index(['assigned_to_id', 'status']);
            $table->index(['related_student_id', 'status']);
            $table->index(['related_teacher_id', 'status']);
        });

        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_report_id')->constrained('issue_reports')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('comment_type')->default('comment');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('issue_report_id');
            $table->index('author_id');
            $table->index('comment_type');
            $table->index('is_internal');
            $table->index('created_at');
            $table->index(['issue_report_id', 'created_at']);
        });

        Schema::create('schedule_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('class_schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->timestampTz('current_starts_at')->nullable();
            $table->timestampTz('current_ends_at')->nullable();
            $table->timestampTz('requested_starts_at');
            $table->timestampTz('requested_ends_at');
            $table->string('timezone');
            $table->text('reason');
            $table->string('status')->default(ScheduleChangeRequest::STATUS_PENDING);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('requester_id');
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('lesson_id');
            $table->index('class_schedule_id');
            $table->index('requested_starts_at');
            $table->index('reviewed_by');
            $table->index(['student_id', 'status']);
            $table->index(['teacher_id', 'status']);
            $table->index(['status', 'requested_starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_change_requests');
        Schema::dropIfExists('issue_comments');
        Schema::dropIfExists('issue_reports');
        Schema::dropIfExists('academic_records');
        Schema::dropIfExists('form_templates');
        Schema::dropIfExists('portal_settings');
        Schema::dropIfExists('operational_announcements');
    }
};
