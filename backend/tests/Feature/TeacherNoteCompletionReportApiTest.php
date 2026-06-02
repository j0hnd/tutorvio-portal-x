<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherNoteCompletionReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

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

        $this->otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->otherTeacher->assignRole('teacher');

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

    public function test_report_detects_missing_notes_and_calculates_completion_rate(): void
    {
        $withNote = $this->createLesson([
            'start_time' => '2026-06-10 09:00:00',
            'end_time' => '2026-06-10 10:00:00',
        ]);
        $this->createLessonNote($withNote, [
            'submitted_at' => '2026-06-10 10:15:00',
        ]);

        $missingNote = $this->createLesson([
            'start_time' => '2026-06-11 09:00:00',
            'end_time' => '2026-06-11 10:00:00',
        ]);
        $this->createLesson([
            'start_time' => '2026-06-12 09:00:00',
            'end_time' => '2026-06-12 10:00:00',
            'status' => Lesson::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/teacher-note-completions?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.summary.total_completed_lessons_requiring_notes', 2)
            ->assertJsonPath('data.summary.lessons_with_teacher_notes', 1)
            ->assertJsonPath('data.summary.lessons_missing_teacher_notes', 1)
            ->assertJsonPath('data.summary.teacher_note_completion_rate', 50)
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_id', $withNote->public_id)
            ->assertJsonPath('data.rows.0.teacher.name', $this->teacher->name)
            ->assertJsonPath('data.rows.0.student.name', 'Ada Student')
            ->assertJsonPath('data.rows.0.course.title', 'Business English')
            ->assertJsonPath('data.rows.0.lesson_status', Lesson::STATUS_COMPLETED)
            ->assertJsonPath('data.rows.0.note_status', 'submitted')
            ->assertJsonPath('data.rows.0.note_submitted_at', '2026-06-10T10:15:00.000000Z')
            ->assertJsonPath('data.rows.1.lesson_id', $missingNote->public_id)
            ->assertJsonPath('data.rows.1.note_status', 'missing')
            ->assertJsonPath('data.rows.1.note_submitted_at', null);
    }

    public function test_report_filters_by_teacher_student_course_and_status(): void
    {
        $matching = $this->createLesson();
        $this->createLessonNote($matching);

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $this->createLesson([
            'student_id' => $otherStudent->id,
            'start_time' => '2026-06-11 09:00:00',
            'end_time' => '2026-06-11 10:00:00',
        ]);

        $this->createLesson([
            'teacher_id' => $this->otherTeacher->id,
            'start_time' => '2026-06-12 09:00:00',
            'end_time' => '2026-06-12 10:00:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/teacher-note-completions?teacher_id='.$this->teacher->id.'&student_id='.$this->student->id.'&course_id='.$this->courseProgram->id.'&status=completed')
            ->assertOk()
            ->assertJsonPath('data.summary.total_completed_lessons_requiring_notes', 1)
            ->assertJsonPath('data.summary.lessons_with_teacher_notes', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_id', $matching->public_id)
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.filters.student_id', $this->student->id)
            ->assertJsonPath('data.filters.course_id', $this->courseProgram->id)
            ->assertJsonPath('data.filters.status', Lesson::STATUS_COMPLETED);

        $this->getJson('/api/v1/admin/reports/teacher-note-completions?status=present')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_teacher_report_is_limited_to_own_lessons(): void
    {
        $ownLesson = $this->createLesson();
        $this->createLesson([
            'teacher_id' => $this->otherTeacher->id,
            'start_time' => '2026-06-11 09:00:00',
            'end_time' => '2026-06-11 10:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/reports/teacher-note-completions')
            ->assertOk()
            ->assertJsonPath('data.summary.total_completed_lessons_requiring_notes', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_id', $ownLesson->public_id);

        $this->getJson('/api/v1/reports/teacher-note-completions?teacher_id='.$this->otherTeacher->id)
            ->assertOk()
            ->assertJsonPath('data.summary.total_completed_lessons_requiring_notes', 0)
            ->assertJsonCount(0, 'data.rows');
    }

    public function test_report_requires_allowed_role_and_permission(): void
    {
        $this->getJson('/api/v1/reports/teacher-note-completions')->assertUnauthorized();

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/reports/teacher-note-completions')->assertForbidden();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/reports/teacher-note-completions')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/reports/teacher-note-completions')->assertOk();

        $this->teacher->roles()->first()->revokePermissionTo('lesson_notes.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Sanctum::actingAs($this->teacher);
        $this->getJson('/api/v1/reports/teacher-note-completions')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLesson(array $overrides = []): Lesson
    {
        return Lesson::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'start_time' => '2026-06-10 09:00:00',
            'end_time' => '2026-06-10 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLessonNote(Lesson $lesson, array $overrides = []): LessonNote
    {
        return LessonNote::create([
            'lesson_id' => $lesson->id,
            'student_id' => $lesson->student_id,
            'teacher_id' => $lesson->teacher_id,
            'author_id' => $lesson->teacher_id,
            'lesson_objective' => 'Practice workplace introductions.',
            'topics_covered' => 'Introductions and follow-up questions.',
            'submitted_at' => '2026-06-10 10:05:00',
            ...$overrides,
        ]);
    }
}
