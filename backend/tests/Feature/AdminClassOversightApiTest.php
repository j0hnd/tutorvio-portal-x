<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\IssueReport;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\LessonRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminClassOversightApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->otherTeacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_list_classes_with_operational_metadata_and_filters(): void
    {
        $courseProgram = CourseProgram::factory()->create();
        CourseProgramStudentAssignment::create([
            'course_program_id' => $courseProgram->id,
            'student_id' => $this->student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
        ]);

        $lesson = $this->createLesson();
        $lessonRecord = $this->createLessonRecord([
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
        ]);
        $lessonNote = $this->createLessonNote([
            'lesson_id' => $lesson->id,
            'lesson_record_id' => $lessonRecord->id,
        ]);
        IssueReport::create([
            'issue_type' => IssueReport::TYPE_CLASS_INCIDENT,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => IssueReport::PRIORITY_NORMAL,
            'reporter_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'related_student_id' => $this->student->id,
            'related_teacher_id' => $this->teacher->id,
            'title' => 'Class connection issue',
            'description' => 'Student could not hear the teacher.',
        ]);

        $otherLesson = $this->createLesson([
            'teacher_id' => $this->otherTeacher->id,
            'start_time' => '2026-06-04 09:00:00',
            'end_time' => '2026-06-04 10:00:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/classes?teacher_id='.$this->teacher->id.'&student_id='.$this->student->id.'&course_program_id='.$courseProgram->id.'&status='.Lesson::STATUS_COMPLETED.'&from=2026-06-01&to=2026-06-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lesson->id)
            ->assertJsonPath('data.0.class_date_time.starts_at', '2026-06-01T09:00:00.000000Z')
            ->assertJsonPath('data.0.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.0.student.id', $this->student->id)
            ->assertJsonPath('data.0.lesson_status', Lesson::STATUS_COMPLETED)
            ->assertJsonPath('data.0.attendance_status', LessonRecord::ATTENDANCE_PRESENT)
            ->assertJsonPath('data.0.teacher_note_available', true)
            ->assertJsonPath('data.0.teacher_note_id', $lessonNote->id)
            ->assertJsonPath('data.0.issue_count', 1);

        $this->getJson('/api/v1/admin/classes?teacher_id='.$this->otherTeacher->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $otherLesson->id)
            ->assertJsonPath('data.0.teacher_note_available', false)
            ->assertJsonPath('data.0.issue_count', 0);
    }

    public function test_admin_can_view_and_review_teacher_notes_for_a_class(): void
    {
        Carbon::setTestNow('2026-06-02 11:00:00');
        $lesson = $this->createLesson();
        $lessonNote = $this->createLessonNote([
            'lesson_id' => $lesson->id,
            'internal_note' => 'Needs more pronunciation drilling.',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/classes/'.$lesson->id.'/teacher-notes')
            ->assertOk()
            ->assertJsonPath('data.id', $lessonNote->id)
            ->assertJsonPath('data.internal_note', 'Needs more pronunciation drilling.')
            ->assertJsonPath('data.review_status', LessonNote::REVIEW_STATUS_PENDING);

        $this->patchJson('/api/v1/admin/teacher-notes/'.$lessonNote->id.'/review', [
            'review_status' => LessonNote::REVIEW_STATUS_FLAGGED,
            'review_note' => 'Follow up with the teacher before sharing feedback.',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $lessonNote->id)
            ->assertJsonPath('data.review_status', LessonNote::REVIEW_STATUS_FLAGGED)
            ->assertJsonPath('data.review_note', 'Follow up with the teacher before sharing feedback.')
            ->assertJsonPath('data.reviewed_by', $this->admin->id)
            ->assertJsonPath('data.reviewed_at', '2026-06-02T11:00:00.000000Z');

        $this->assertDatabaseHas('lesson_notes', [
            'id' => $lessonNote->id,
            'review_status' => LessonNote::REVIEW_STATUS_FLAGGED,
            'review_note' => 'Follow up with the teacher before sharing feedback.',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => '2026-06-02 11:00:00',
        ]);
    }

    public function test_staff_and_students_cannot_bypass_role_and_permission_rules(): void
    {
        $lesson = $this->createLesson();
        $lessonNote = $this->createLessonNote(['lesson_id' => $lesson->id]);

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/classes')
            ->assertForbidden();
        $this->getJson('/api/v1/admin/classes/'.$lesson->id.'/teacher-notes')
            ->assertForbidden();
        $this->patchJson('/api/v1/admin/teacher-notes/'.$lessonNote->id.'/review', [
            'review_status' => LessonNote::REVIEW_STATUS_REVIEWED,
        ])->assertForbidden();

        $staff->givePermissionTo(['classes.view', 'lesson_notes.view']);

        $this->getJson('/api/v1/admin/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/classes/'.$lesson->id.'/teacher-notes')
            ->assertOk()
            ->assertJsonPath('data.id', $lessonNote->id);
        $this->patchJson('/api/v1/admin/teacher-notes/'.$lessonNote->id.'/review', [
            'review_status' => LessonNote::REVIEW_STATUS_REVIEWED,
        ])->assertForbidden();

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/admin/classes')
            ->assertForbidden();
        $this->getJson('/api/v1/admin/classes/'.$lesson->id.'/teacher-notes')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLesson(array $overrides = []): Lesson
    {
        return Lesson::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'start_time' => '2026-06-01 09:00:00',
            'end_time' => '2026-06-01 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLessonRecord(array $overrides = []): LessonRecord
    {
        return LessonRecord::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLessonNote(array $overrides = []): LessonNote
    {
        return LessonNote::create([
            'lesson_id' => $overrides['lesson_id'] ?? $this->createLesson()->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'author_id' => $this->teacher->id,
            'topics_covered' => 'Introductions and follow-up questions.',
            'internal_note' => 'Keep correction direct but brief.',
            'submitted_at' => '2026-06-01 10:10:00',
            ...$overrides,
        ]);
    }
}
