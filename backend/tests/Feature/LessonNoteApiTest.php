<?php

namespace Tests\Feature;

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

class LessonNoteApiTest extends TestCase
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

    public function test_teacher_can_create_lesson_note_for_assigned_completed_lesson(): void
    {
        Carbon::setTestNow('2026-06-01 10:10:00');
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson();
        $lessonRecord = $this->createLessonRecord();

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lesson->public_id,
            'lesson_record_id' => $lessonRecord->id,
            'lesson_objective' => 'Practice workplace introductions.',
            'topics_covered' => 'Introductions and follow-up questions.',
            'homework_assignment' => 'Prepare a two-minute self introduction.',
            'internal_note' => 'Keep correction direct but brief.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.lesson_id', $lesson->public_id)
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.author_id', $this->teacher->id)
            ->assertJsonPath('data.lesson_record_id', $lessonRecord->id)
            ->assertJsonPath('data.lesson_objective', 'Practice workplace introductions.')
            ->assertJsonPath('data.internal_note', 'Keep correction direct but brief.')
            ->assertJsonPath('data.lesson.id', $lesson->public_id)
            ->assertJsonPath('data.lesson.status', Lesson::STATUS_COMPLETED)
            ->assertJsonPath('data.student.id', $this->student->public_id)
            ->assertJsonPath('data.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.author.id', $this->teacher->id);

        $this->assertDatabaseHas('lesson_notes', [
            'lesson_id' => $lesson->public_id,
            'student_id' => $this->student->public_id,
            'teacher_id' => $this->teacher->id,
            'author_id' => $this->teacher->id,
            'lesson_record_id' => $lessonRecord->id,
            'submitted_at' => '2026-06-01 10:10:00',
        ]);
    }

    public function test_create_lesson_note_requires_lesson_and_note_content(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/lesson-notes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'lesson_id',
                'lesson_objective',
            ]);
    }

    public function test_lesson_note_payload_fields_must_be_valid_strings(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson();

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lesson->public_id,
            'topics_covered' => ['Warm-up conversation.'],
            'homework_assignment' => ['Prepare answers.'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'topics_covered',
                'homework_assignment',
            ]);

        $lessonNote = $this->createLessonNote([
            'lesson_id' => $this->createLesson([
                'start_time' => '2026-06-02 09:00:00',
                'end_time' => '2026-06-02 10:00:00',
            ])->id,
        ]);

        $this->patchJson('/api/v1/lesson-notes/'.$lessonNote->public_id, [
            'internal_note' => ['Private coaching note.'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('internal_note');
    }

    public function test_teacher_cannot_create_note_for_unassigned_or_cancelled_lesson(): void
    {
        Sanctum::actingAs($this->teacher);

        $unassignedLesson = $this->createLesson([
            'teacher_id' => $this->otherTeacher->id,
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $unassignedLesson->id,
            'topics_covered' => 'Warm-up conversation.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');

        $cancelledLesson = $this->createLesson([
            'status' => Lesson::STATUS_CANCELLED,
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $cancelledLesson->id,
            'topics_covered' => 'Warm-up conversation.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    public function test_lesson_note_requires_valid_student_and_teacher_users_on_lesson(): void
    {
        Sanctum::actingAs($this->admin);

        $nonStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $lessonWithoutStudent = $this->createLesson([
            'student_id' => $nonStudent->id,
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lessonWithoutStudent->id,
            'topics_covered' => 'Warm-up conversation.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');

        $nonTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $lessonWithoutTeacher = $this->createLesson([
            'teacher_id' => $nonTeacher->id,
            'start_time' => '2026-06-02 09:00:00',
            'end_time' => '2026-06-02 10:00:00',
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lessonWithoutTeacher->id,
            'topics_covered' => 'Warm-up conversation.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    public function test_lesson_record_must_match_lesson_student_and_teacher(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson();
        $mismatchedRecord = $this->createLessonRecord([
            'teacher_id' => $this->otherTeacher->id,
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lesson->public_id,
            'lesson_record_id' => $mismatchedRecord->id,
            'topics_covered' => 'Warm-up conversation.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_record_id');
    }

    public function test_lesson_note_cannot_duplicate_lesson_or_lesson_record_links(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson();
        $lessonRecord = $this->createLessonRecord();
        $this->createLessonNote([
            'lesson_id' => $lesson->public_id,
            'lesson_record_id' => $lessonRecord->id,
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lesson->public_id,
            'topics_covered' => 'Duplicate lesson note.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');

        $newLesson = $this->createLesson([
            'start_time' => '2026-06-02 09:00:00',
            'end_time' => '2026-06-02 10:00:00',
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $newLesson->id,
            'lesson_record_id' => $lessonRecord->id,
            'topics_covered' => 'Duplicate record note.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_record_id');
    }

    public function test_update_rejects_invalid_or_already_linked_lesson_record(): void
    {
        Sanctum::actingAs($this->teacher);

        $lessonNote = $this->createLessonNote();
        $cancelledLessonRecord = $this->createLessonRecord([
            'lesson_status' => LessonRecord::STATUS_CANCELLED,
        ]);

        $this->patchJson('/api/v1/lesson-notes/'.$lessonNote->public_id, [
            'lesson_record_id' => $cancelledLessonRecord->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_record_id');

        $otherLesson = $this->createLesson([
            'start_time' => '2026-06-03 09:00:00',
            'end_time' => '2026-06-03 10:00:00',
        ]);
        $linkedLessonRecord = $this->createLessonRecord([
            'scheduled_date' => '2026-06-03',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $this->createLessonNote([
            'lesson_id' => $otherLesson->id,
            'lesson_record_id' => $linkedLessonRecord->id,
            'submitted_at' => '2026-06-03 10:10:00',
        ]);

        $this->patchJson('/api/v1/lesson-notes/'.$lessonNote->public_id, [
            'lesson_record_id' => $linkedLessonRecord->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_record_id');
    }

    public function test_teacher_can_view_update_and_list_only_their_own_notes(): void
    {
        Sanctum::actingAs($this->teacher);

        $ownNote = $this->createLessonNote();
        $otherNote = $this->createLessonNote([
            'lesson_id' => $this->createLesson([
                'teacher_id' => $this->otherTeacher->id,
                'start_time' => '2026-06-02 09:00:00',
                'end_time' => '2026-06-02 10:00:00',
            ])->id,
            'teacher_id' => $this->otherTeacher->id,
        ]);

        $this->getJson('/api/v1/lesson-notes/'.$ownNote->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownNote->public_id);

        $this->patchJson('/api/v1/lesson-notes/'.$ownNote->public_id, [
            'recommendation_for_next_lesson' => 'Review follow-up questions in role play.',
        ])
            ->assertOk()
            ->assertJsonPath('data.recommendation_for_next_lesson', 'Review follow-up questions in role play.');

        $this->getJson('/api/v1/lesson-notes/'.$otherNote->public_id)
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-notes/'.$otherNote->public_id, [
            'topics_covered' => 'Updated by the wrong teacher.',
        ])
            ->assertForbidden();

        $this->getJson('/api/v1/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownNote->public_id);
    }

    public function test_lesson_and_student_note_lists_are_available_for_progress_history(): void
    {
        Sanctum::actingAs($this->admin);

        $lesson = $this->createLesson();
        $lessonNote = $this->createLessonNote(['lesson_id' => $lesson->public_id]);

        $this->getJson('/api/v1/lessons/'.$lesson->public_id.'/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonNote->public_id);

        $this->getJson('/api/v1/students/'.$this->student->public_id.'/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonNote->public_id);
    }

    public function test_admin_can_review_all_lesson_notes_with_internal_notes_and_filters(): void
    {
        $firstLesson = $this->createLesson();
        $firstNote = $this->createLessonNote([
            'lesson_id' => $firstLesson->id,
            'internal_note' => 'First internal review note.',
            'submitted_at' => '2026-06-01 10:10:00',
        ]);

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $otherLesson = $this->createLesson([
            'student_id' => $otherStudent->public_id,
            'teacher_id' => $this->otherTeacher->id,
            'start_time' => '2026-06-02 09:00:00',
            'end_time' => '2026-06-02 10:00:00',
        ]);
        $secondNote = $this->createLessonNote([
            'lesson_id' => $otherLesson->id,
            'student_id' => $otherStudent->public_id,
            'teacher_id' => $this->otherTeacher->id,
            'author_id' => $this->otherTeacher->id,
            'internal_note' => 'Second internal review note.',
            'submitted_at' => '2026-06-02 10:10:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/lesson-notes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $secondNote->id)
            ->assertJsonPath('data.0.internal_note', 'Second internal review note.')
            ->assertJsonPath('data.1.id', $firstNote->id)
            ->assertJsonPath('data.1.internal_note', 'First internal review note.');

        $this->getJson('/api/v1/lesson-notes?teacher_id='.$this->otherTeacher->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $secondNote->id)
            ->assertJsonPath('data.0.teacher_id', $this->otherTeacher->id);
    }

    public function test_submitted_lesson_note_is_auto_linked_to_matching_progress_record(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson();
        $lessonRecord = $this->createLessonRecord([
            'lesson_notes' => 'Existing progress note stays on the progress record.',
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lesson->public_id,
            'topics_covered' => 'Introductions and follow-up questions.',
            'recommendation_for_next_lesson' => 'Practice short workplace answers.',
            'internal_note' => 'Needs more wait time before corrections.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.lesson_record_id', $lessonRecord->id);

        $this->assertDatabaseHas('lesson_notes', [
            'lesson_id' => $lesson->public_id,
            'lesson_record_id' => $lessonRecord->id,
            'topics_covered' => 'Introductions and follow-up questions.',
        ]);

        $this->assertDatabaseHas('lesson_records', [
            'id' => $lessonRecord->id,
            'lesson_notes' => 'Existing progress note stays on the progress record.',
        ]);
    }

    public function test_progress_records_include_student_visible_lesson_note_summary(): void
    {
        $lesson = $this->createLesson();
        $lessonRecord = $this->createLessonRecord();
        $lessonNote = $this->createLessonNote([
            'lesson_id' => $lesson->public_id,
            'lesson_record_id' => $lessonRecord->id,
            'topics_covered' => 'Introductions and follow-up questions.',
            'recommendation_for_next_lesson' => 'Practice short workplace answers.',
            'internal_note' => 'Needs more wait time before corrections.',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/lesson-records')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lesson_note.id', $lessonNote->public_id)
            ->assertJsonPath('data.0.lesson_note.topics_covered', 'Introductions and follow-up questions.')
            ->assertJsonPath('data.0.lesson_note.recommendation_for_next_lesson', 'Practice short workplace answers.')
            ->assertJsonMissingPath('data.0.lesson_note.internal_note');
    }

    public function test_students_only_receive_student_visible_note_fields_for_their_own_lessons(): void
    {
        $lesson = $this->createLesson();
        $lessonNote = $this->createLessonNote([
            'lesson_id' => $lesson->public_id,
            'lesson_objective' => 'Practice workplace introductions.',
            'internal_note' => 'Do not share this coaching context.',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/lesson-notes/'.$lessonNote->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $lessonNote->public_id)
            ->assertJsonPath('data.lesson_objective', 'Practice workplace introductions.')
            ->assertJsonMissingPath('data.internal_note');

        $this->getJson('/api/v1/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonNote->public_id)
            ->assertJsonMissingPath('data.0.internal_note');

        $this->getJson('/api/v1/lessons/'.$lesson->public_id.'/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonNote->public_id)
            ->assertJsonMissingPath('data.0.internal_note');

        $this->getJson('/api/v1/students/'.$this->student->public_id.'/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonNote->public_id)
            ->assertJsonMissingPath('data.0.internal_note');
    }

    public function test_student_cannot_view_another_students_note(): void
    {
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $otherLesson = $this->createLesson([
            'student_id' => $otherStudent->public_id,
            'start_time' => '2026-06-02 09:00:00',
            'end_time' => '2026-06-02 10:00:00',
        ]);
        $otherNote = $this->createLessonNote([
            'lesson_id' => $otherLesson->id,
            'student_id' => $otherStudent->public_id,
            'internal_note' => 'Private note for another student.',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/lesson-notes/'.$otherNote->public_id)
            ->assertForbidden();

        $this->getJson('/api/v1/students/'.$otherStudent->public_id.'/lesson-notes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_and_assigned_teacher_can_view_internal_notes(): void
    {
        $lessonNote = $this->createLessonNote([
            'internal_note' => 'Keep correction direct but brief.',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/lesson-notes/'.$lessonNote->public_id)
            ->assertOk()
            ->assertJsonPath('data.internal_note', 'Keep correction direct but brief.');

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/lesson-notes/'.$lessonNote->public_id)
            ->assertOk()
            ->assertJsonPath('data.internal_note', 'Keep correction direct but brief.');
    }

    public function test_staff_lesson_note_access_follows_permissions(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $lessonNote = $this->createLessonNote([
            'internal_note' => 'Visible only with lesson note permission.',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/lesson-notes')
            ->assertForbidden();

        $this->getJson('/api/v1/lesson-notes/'.$lessonNote->public_id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $this->createLesson([
                'start_time' => '2026-06-02 09:00:00',
                'end_time' => '2026-06-02 10:00:00',
            ])->id,
            'topics_covered' => 'Warm-up conversation.',
        ])->assertForbidden();

        $this->patchJson('/api/v1/lesson-notes/'.$lessonNote->public_id, [
            'topics_covered' => 'Staff attempted update.',
        ])->assertForbidden();

        $staff->givePermissionTo([
            'lesson_notes.view',
            'lesson_notes.create',
            'lesson_notes.update',
        ]);

        $this->getJson('/api/v1/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.internal_note', 'Visible only with lesson note permission.');

        $this->getJson('/api/v1/lesson-notes/'.$lessonNote->public_id)
            ->assertOk()
            ->assertJsonPath('data.internal_note', 'Visible only with lesson note permission.');

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $this->createLesson([
                'start_time' => '2026-06-03 09:00:00',
                'end_time' => '2026-06-03 10:00:00',
            ])->id,
            'topics_covered' => 'Warm-up conversation.',
        ])->assertCreated();

        $this->patchJson('/api/v1/lesson-notes/'.$lessonNote->public_id, [
            'topics_covered' => 'Staff updated note.',
        ])
            ->assertOk()
            ->assertJsonPath('data.topics_covered', 'Staff updated note.');
    }

    public function test_teacher_only_sees_their_own_pending_and_missing_lesson_notes(): void
    {
        $ownPendingLesson = $this->createLesson();
        $ownNotedLesson = $this->createLesson([
            'start_time' => '2026-06-02 09:00:00',
            'end_time' => '2026-06-02 10:00:00',
        ]);
        $otherTeacherPendingLesson = $this->createLesson([
            'teacher_id' => $this->otherTeacher->id,
            'start_time' => '2026-06-03 09:00:00',
            'end_time' => '2026-06-03 10:00:00',
        ]);

        $this->createLessonNote([
            'lesson_id' => $ownNotedLesson->id,
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/lesson-notes/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lesson_id', $ownPendingLesson->id)
            ->assertJsonPath('data.0.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.0.note_required', true)
            ->assertJsonPath('data.0.note_status', 'missing')
            ->assertJsonPath('meta.pending_notes', 1)
            ->assertJsonPath('meta.missing_notes', 1)
            ->assertJsonPath('meta.completed_lessons_requiring_notes', 2)
            ->assertJsonMissingPath('data.1');

        $this->assertDatabaseMissing('lesson_notes', [
            'lesson_id' => $ownPendingLesson->id,
        ]);
        $this->assertSame($this->otherTeacher->id, $otherTeacherPendingLesson->teacher_id);
    }

    public function test_admin_can_review_all_pending_and_missing_lesson_notes(): void
    {
        $ownPendingLesson = $this->createLesson();
        $otherTeacherPendingLesson = $this->createLesson([
            'teacher_id' => $this->otherTeacher->id,
            'start_time' => '2026-06-02 09:00:00',
            'end_time' => '2026-06-02 10:00:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/lesson-notes/pending')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.lesson_id', $otherTeacherPendingLesson->id)
            ->assertJsonPath('data.1.lesson_id', $ownPendingLesson->id)
            ->assertJsonPath('meta.pending_notes', 2)
            ->assertJsonPath('meta.missing_notes', 2)
            ->assertJsonPath('meta.completed_lessons_requiring_notes', 2);
    }

    public function test_completed_lesson_with_unsubmitted_note_is_still_missing(): void
    {
        $lesson = $this->createLesson();
        $this->createLessonNote([
            'lesson_id' => $lesson->public_id,
            'submitted_at' => null,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/lesson-notes/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lesson_id', $lesson->public_id)
            ->assertJsonPath('meta.pending_notes', 1)
            ->assertJsonPath('meta.missing_notes', 1)
            ->assertJsonPath('meta.completed_lessons_requiring_notes', 1);
    }

    public function test_pending_lesson_notes_exclude_statuses_that_do_not_require_notes(): void
    {
        $completedLesson = $this->createLesson();
        $cancelledLesson = $this->createLesson([
            'status' => Lesson::STATUS_CANCELLED,
            'start_time' => '2026-06-02 09:00:00',
            'end_time' => '2026-06-02 10:00:00',
        ]);
        $rescheduledLesson = $this->createLesson([
            'status' => Lesson::STATUS_RESCHEDULED,
            'start_time' => '2026-06-03 09:00:00',
            'end_time' => '2026-06-03 10:00:00',
        ]);
        $missedByStudentLesson = $this->createLesson([
            'status' => Lesson::STATUS_MISSED_BY_STUDENT,
            'start_time' => '2026-06-04 09:00:00',
            'end_time' => '2026-06-04 10:00:00',
        ]);
        $missedByTeacherLesson = $this->createLesson([
            'status' => Lesson::STATUS_MISSED_BY_TEACHER,
            'start_time' => '2026-06-05 09:00:00',
            'end_time' => '2026-06-05 10:00:00',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/lesson-notes/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lesson_id', $completedLesson->id)
            ->assertJsonPath('meta.pending_notes', 1)
            ->assertJsonPath('meta.missing_notes', 1)
            ->assertJsonPath('meta.completed_lessons_requiring_notes', 1)
            ->assertJsonPath('meta.note_required_statuses', [Lesson::STATUS_COMPLETED])
            ->assertJsonPath('meta.note_not_required_statuses', [
                Lesson::STATUS_SCHEDULED,
                Lesson::STATUS_PENDING_CONFIRMATION,
                Lesson::STATUS_EXPIRED,
                Lesson::STATUS_CANCELLED,
                Lesson::STATUS_RESCHEDULED,
                Lesson::STATUS_MISSED_BY_STUDENT,
                Lesson::STATUS_MISSED_BY_TEACHER,
            ]);

        $this->assertFalse($cancelledLesson->requiresLessonNote());
        $this->assertFalse($rescheduledLesson->requiresLessonNote());
        $this->assertFalse($missedByStudentLesson->requiresLessonNote());
        $this->assertFalse($missedByTeacherLesson->requiresLessonNote());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLesson(array $overrides = []): Lesson
    {
        return Lesson::create([
            'student_id' => $this->student->public_id,
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
            'student_id' => $this->student->public_id,
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
            'student_id' => $this->student->public_id,
            'teacher_id' => $this->teacher->id,
            'author_id' => $this->teacher->id,
            'topics_covered' => 'Introductions and follow-up questions.',
            'internal_note' => 'Keep correction direct but brief.',
            'submitted_at' => '2026-06-01 10:10:00',
            ...$overrides,
        ]);
    }
}
