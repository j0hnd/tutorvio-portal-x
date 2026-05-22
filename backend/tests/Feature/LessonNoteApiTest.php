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
            'lesson_id' => $lesson->id,
            'lesson_record_id' => $lessonRecord->id,
            'lesson_objective' => 'Practice workplace introductions.',
            'topics_covered' => 'Introductions and follow-up questions.',
            'homework_assignment' => 'Prepare a two-minute self introduction.',
            'internal_note' => 'Keep correction direct but brief.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.lesson_id', $lesson->id)
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.author_id', $this->teacher->id)
            ->assertJsonPath('data.lesson_record_id', $lessonRecord->id)
            ->assertJsonPath('data.lesson_objective', 'Practice workplace introductions.')
            ->assertJsonPath('data.internal_note', 'Keep correction direct but brief.');

        $this->assertDatabaseHas('lesson_notes', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->student->id,
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

    public function test_lesson_record_must_match_lesson_student_and_teacher(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson();
        $mismatchedRecord = $this->createLessonRecord([
            'teacher_id' => $this->otherTeacher->id,
        ]);

        $this->postJson('/api/v1/lesson-notes', [
            'lesson_id' => $lesson->id,
            'lesson_record_id' => $mismatchedRecord->id,
            'topics_covered' => 'Warm-up conversation.',
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

        $this->getJson('/api/v1/lesson-notes/'.$ownNote->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownNote->id);

        $this->patchJson('/api/v1/lesson-notes/'.$ownNote->id, [
            'recommendation_for_next_lesson' => 'Review follow-up questions in role play.',
        ])
            ->assertOk()
            ->assertJsonPath('data.recommendation_for_next_lesson', 'Review follow-up questions in role play.');

        $this->getJson('/api/v1/lesson-notes/'.$otherNote->id)
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-notes/'.$otherNote->id, [
            'topics_covered' => 'Updated by the wrong teacher.',
        ])
            ->assertForbidden();

        $this->getJson('/api/v1/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownNote->id);
    }

    public function test_lesson_and_student_note_lists_are_available_for_progress_history(): void
    {
        Sanctum::actingAs($this->admin);

        $lesson = $this->createLesson();
        $lessonNote = $this->createLessonNote(['lesson_id' => $lesson->id]);

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonNote->id);

        $this->getJson('/api/v1/students/'.$this->student->id.'/lesson-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonNote->id);
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
            'submitted_at' => '2026-06-01 10:10:00',
            ...$overrides,
        ]);
    }
}
