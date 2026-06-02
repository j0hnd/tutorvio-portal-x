<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LessonCompletionReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private CourseProgram $courseProgram;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create([
            'name' => 'Ada Student',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->student->assignRole('student');

        $this->courseProgram = CourseProgram::factory()->create([
            'title' => 'Business English',
            'placement_level' => 'B1',
        ]);

        CourseProgramStudentAssignment::create([
            'course_program_id' => $this->courseProgram->id,
            'student_id' => $this->student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);
    }

    public function test_lesson_completion_report_calculates_completion_rate_and_returns_rows(): void
    {
        $completed = $this->createLessonRecord([
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
            'completed_at' => '2026-06-10 10:05:00',
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-11',
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
            'completed_at' => '2026-06-11 10:05:00',
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-12',
            'lesson_status' => LessonRecord::STATUS_SCHEDULED,
            'is_completed' => false,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-13',
            'lesson_status' => LessonRecord::STATUS_CANCELLED,
            'is_completed' => false,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/lesson-completions?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.summary.total_lessons', 4)
            ->assertJsonPath('data.summary.completed_count', 2)
            ->assertJsonPath('data.summary.pending_upcoming_count', 1)
            ->assertJsonPath('data.summary.cancelled_rescheduled_missed_count', 1)
            ->assertJsonPath('data.summary.completion_rate', 50)
            ->assertJsonCount(4, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_id', $completed->public_id)
            ->assertJsonPath('data.rows.0.lesson_record_id', $completed->public_id)
            ->assertJsonPath('data.rows.0.lesson_datetime', '2026-06-10 09:00:00')
            ->assertJsonPath('data.rows.0.student.name', 'Ada Student')
            ->assertJsonPath('data.rows.0.teacher.id', $this->teacher->public_id)
            ->assertJsonPath('data.rows.0.course.title', 'Business English')
            ->assertJsonPath('data.rows.0.lesson_type', LessonRecord::TYPE_BUSINESS_ENGLISH)
            ->assertJsonPath('data.rows.0.lesson_status', LessonRecord::STATUS_COMPLETED)
            ->assertJsonPath('data.rows.0.completion_status', 'completed')
            ->assertJsonPath('data.rows.0.completed_at', '2026-06-10T10:05:00.000000Z');
    }

    public function test_lesson_completion_report_filters_by_status(): void
    {
        $matching = $this->createLessonRecord([
            'lesson_status' => LessonRecord::STATUS_RESCHEDULED,
            'is_completed' => false,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-11',
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
            'completed_at' => '2026-06-11 10:05:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/lesson-completions?status=rescheduled')
            ->assertOk()
            ->assertJsonPath('data.summary.total_lessons', 1)
            ->assertJsonPath('data.summary.completed_count', 0)
            ->assertJsonPath('data.summary.cancelled_rescheduled_missed_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_id', $matching->public_id)
            ->assertJsonPath('data.filters.status', LessonRecord::STATUS_RESCHEDULED);

        $this->getJson('/api/v1/admin/reports/lesson-completions?status=present')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_lesson_completion_report_requires_role_and_permission(): void
    {
        $this->getJson('/api/v1/admin/reports/lesson-completions')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports/lesson-completions')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/lesson-completions')->assertOk();

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/admin/reports/lesson-completions')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLessonRecord(array $overrides = []): LessonRecord
    {
        return LessonRecord::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_SCHEDULED,
            'attendance_status' => null,
            'is_completed' => false,
            'completed_at' => null,
            ...$overrides,
        ]);
    }
}
