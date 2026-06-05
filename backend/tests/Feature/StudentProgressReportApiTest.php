<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\StudentProgressRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentProgressReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private User $otherStudent;

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

        $this->otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->otherTeacher->assignRole('teacher');

        $this->student = User::factory()->create([
            'name' => 'Ada Student',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->student->assignRole('student');
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
            'current_level' => 'B1',
            'english_level' => 'A2',
        ]);

        $this->otherStudent = User::factory()->create([
            'name' => 'Other Student',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->otherStudent->assignRole('student');
        $this->otherStudent->studentProfile()->create([
            'assigned_teacher_id' => $this->otherTeacher->id,
            'current_level' => 'A2',
            'english_level' => 'A1',
        ]);

        $this->courseProgram = CourseProgram::factory()->create([
            'title' => 'Business English',
            'placement_level' => 'B1',
        ]);

        CourseProgramStudentAssignment::create([
            'course_program_id' => $this->courseProgram->id,
            'student_id' => $this->student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-06-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-06-01',
        ]);
    }

    public function test_admin_can_view_student_progress_report_summary_and_rows(): void
    {
        $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_SPEAKING => 'Can present a short update.',
            ],
            'teacher_comments' => 'Needs fewer prompts.',
            'milestone_achievements' => ['Completed first presentation.'],
            'lesson_completion_count' => 4,
            'progress_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
            'recorded_at' => '2026-06-10 09:00:00',
        ]);
        $latest = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_GRAMMAR,
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_GRAMMAR => 'Uses past tense accurately.',
            ],
            'teacher_comments' => 'Ready for complex sentence work.',
            'lesson_completion_count' => 7,
            'progress_status' => StudentProgressRecord::STATUS_COMPLETED,
            'recorded_at' => '2026-06-15 09:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->otherTeacher->id,
            'progress_status' => StudentProgressRecord::STATUS_NEEDS_SUPPORT,
            'recorded_at' => '2026-06-16 09:00:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/student-progress')
            ->assertOk()
            ->assertJsonPath('data.summary.students_count', 2)
            ->assertJsonPath('data.summary.progress_records_count', 3)
            ->assertJsonPath('data.summary.teacher_comments_count', 3)
            ->assertJsonPath('data.summary.completed_lessons_count', 7 + 1)
            ->assertJsonPath('data.summary.status_counts.completed', 1)
            ->assertJsonPath('data.summary.status_counts.in_progress', 1)
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $this->student->public_id)
            ->assertJsonPath('data.rows.0.student_name', 'Ada Student')
            ->assertJsonPath('data.rows.0.course.id', $this->courseProgram->public_id)
            ->assertJsonPath('data.rows.0.course.title', 'Business English')
            ->assertJsonPath('data.rows.0.assigned_teacher.id', $this->teacher->public_id)
            ->assertJsonPath('data.rows.0.progress_level', 'B1')
            ->assertJsonPath('data.rows.0.previous_level', 'A2')
            ->assertJsonPath('data.rows.0.milestone', 'Completed first presentation.')
            ->assertJsonPath('data.rows.0.skill_progress.grammar.record_id', $latest->public_id)
            ->assertJsonPath('data.rows.0.teacher_comments_count', 2)
            ->assertJsonPath('data.rows.0.completed_lessons_count', 7)
            ->assertJsonPath('data.rows.0.last_progress_update_date', '2026-06-15')
            ->assertJsonPath('data.rows.0.current_progress_status', StudentProgressRecord::STATUS_COMPLETED);
    }

    public function test_student_progress_report_filters_rows(): void
    {
        $matching = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'progress_status' => StudentProgressRecord::STATUS_COMPLETED,
            'recorded_at' => '2026-06-15 09:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'progress_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
            'recorded_at' => '2026-07-01 09:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->otherTeacher->id,
            'progress_status' => StudentProgressRecord::STATUS_COMPLETED,
            'recorded_at' => '2026-06-15 09:00:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/student-progress?date_from=2026-06-01&date_to=2026-06-30&teacher_id='.$this->teacher->id.'&student_id='.$this->student->id.'&course_id='.$this->courseProgram->id.'&status='.StudentProgressRecord::STATUS_COMPLETED)
            ->assertOk()
            ->assertJsonPath('data.summary.students_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $this->student->public_id)
            ->assertJsonPath('data.rows.0.current_progress_status', StudentProgressRecord::STATUS_COMPLETED)
            ->assertJsonPath('data.rows.0.skill_progress.speaking.record_id', $matching->public_id)
            ->assertJsonPath('data.filters.date_from', '2026-06-01')
            ->assertJsonPath('data.filters.date_to', '2026-06-30')
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->public_id)
            ->assertJsonPath('data.filters.student_id', $this->student->public_id)
            ->assertJsonPath('data.filters.course_id', $this->courseProgram->public_id)
            ->assertJsonPath('data.filters.status', StudentProgressRecord::STATUS_COMPLETED);

        $this->getJson('/api/v1/admin/reports/student-progress?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_student_can_only_access_own_progress_report(): void
    {
        $ownRecord = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'teacher_comments' => 'Student-visible note.',
            'recorded_at' => '2026-06-15 09:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->otherTeacher->id,
            'teacher_comments' => 'Private other student note.',
            'recorded_at' => '2026-06-16 09:00:00',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/reports/student-progress')
            ->assertOk()
            ->assertJsonPath('data.summary.students_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $this->student->public_id)
            ->assertJsonPath('data.rows.0.skill_progress.speaking.record_id', $ownRecord->public_id);

        $this->getJson('/api/v1/reports/student-progress?student_id='.$this->student->id)
            ->assertOk()
            ->assertJsonPath('data.rows.0.student_id', $this->student->public_id);

        $this->getJson('/api/v1/reports/student-progress?student_id='.$this->otherStudent->id)
            ->assertForbidden();

        $this->getJson('/api/v1/admin/reports/student-progress')
            ->assertForbidden();
    }

    public function test_teacher_report_access_is_limited_to_assigned_students(): void
    {
        $assignedRecord = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->otherTeacher->id,
            'recorded_at' => '2026-06-15 09:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->teacher->id,
            'recorded_at' => '2026-06-16 09:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/reports/student-progress')
            ->assertOk()
            ->assertJsonPath('data.summary.students_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $this->student->public_id)
            ->assertJsonPath('data.rows.0.skill_progress.speaking.record_id', $assignedRecord->public_id);
    }

    public function test_student_progress_report_requires_report_permission_for_admin_route(): void
    {
        $this->getJson('/api/v1/admin/reports/student-progress')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports/student-progress')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/student-progress')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProgressRecord(array $overrides = []): StudentProgressRecord
    {
        return StudentProgressRecord::factory()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_SPEAKING => 'Speaking progress summary.',
            ],
            'teacher_comments' => 'Teacher comment.',
            'milestone_achievements' => [],
            'lesson_completion_count' => 1,
            'progress_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            ...$overrides,
        ]);
    }
}
