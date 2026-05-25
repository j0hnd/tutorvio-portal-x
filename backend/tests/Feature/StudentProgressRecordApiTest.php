<?php

namespace Tests\Feature;

use App\Models\StudentProgressRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentProgressRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private User $otherStudent;

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
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        $this->otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->otherStudent->assignRole('student');
        $this->otherStudent->studentProfile()->create([
            'assigned_teacher_id' => $this->otherTeacher->id,
        ]);
    }

    public function test_admin_can_create_view_update_and_delete_progress_record(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/student-progress-records', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_SPEAKING => 'Can sustain a short conversation.',
            ],
            'speaking_confidence_rating' => StudentProgressRecord::RATING_CONFIDENT,
            'lesson_completion_count' => 8,
            'teacher_comments' => 'Ready for longer speaking prompts.',
            'milestone_achievements' => ['Completed a mock interview.'],
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_UP,
            'goals_completed' => ['Introduce self confidently.'],
            'goals_in_progress' => ['Answer follow-up questions.'],
            'goal_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
            'recorded_at' => '2026-06-15 14:30:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.skill_area', StudentProgressRecord::SKILL_SPEAKING)
            ->assertJsonPath('data.progress_status', StudentProgressRecord::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.goal_status', StudentProgressRecord::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.created_by', $this->admin->id);

        $recordId = $response->json('data.id');

        $this->getJson("/api/v1/student-progress-records/{$recordId}")
            ->assertOk()
            ->assertJsonPath('data.id', $recordId)
            ->assertJsonPath('data.student.id', $this->student->id)
            ->assertJsonPath('data.teacher.id', $this->teacher->id);

        $this->patchJson("/api/v1/student-progress-records/{$recordId}", [
            'skill_area' => StudentProgressRecord::SKILL_FLUENCY,
            'speaking_confidence_rating' => StudentProgressRecord::RATING_STRONG,
            'progress_status' => StudentProgressRecord::STATUS_COMPLETED,
        ])
            ->assertOk()
            ->assertJsonPath('data.skill_area', StudentProgressRecord::SKILL_FLUENCY)
            ->assertJsonPath('data.speaking_confidence_rating', StudentProgressRecord::RATING_STRONG)
            ->assertJsonPath('data.progress_status', StudentProgressRecord::STATUS_COMPLETED)
            ->assertJsonPath('data.updated_by', $this->admin->id);

        $this->deleteJson("/api/v1/student-progress-records/{$recordId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('student_progress_records', [
            'id' => $recordId,
        ]);
    }

    public function test_list_supports_filters_and_pagination(): void
    {
        Sanctum::actingAs($this->admin);

        $matching = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_GRAMMAR,
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_UP,
            'progress_status' => StudentProgressRecord::STATUS_COMPLETED,
            'recorded_at' => '2026-06-10 09:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_GRAMMAR,
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_MAINTAINED,
            'progress_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
            'recorded_at' => '2026-06-11 09:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->otherTeacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_UP,
            'progress_status' => StudentProgressRecord::STATUS_COMPLETED,
            'recorded_at' => '2026-06-10 09:00:00',
        ]);

        $this->getJson('/api/v1/student-progress-records?student_id='.$this->student->id.'&teacher_id='.$this->teacher->id.'&skill_area='.StudentProgressRecord::SKILL_GRAMMAR.'&date_from=2026-06-01&date_to=2026-06-30&level_movement='.StudentProgressRecord::LEVEL_MOVEMENT_UP.'&goal_status='.StudentProgressRecord::STATUS_COMPLETED.'&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 1);
    }

    public function test_teacher_can_manage_records_for_assigned_students_only(): void
    {
        Sanctum::actingAs($this->teacher);

        $record = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'recorded_at' => '2026-06-10 09:00:00',
        ]);
        $otherRecord = $this->createProgressRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->otherTeacher->id,
            'recorded_at' => '2026-06-11 09:00:00',
        ]);

        $this->getJson('/api/v1/student-progress-records?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $record->id);

        $this->patchJson("/api/v1/student-progress-records/{$record->id}", [
            'teacher_comments' => 'Good recovery after correction.',
        ])
            ->assertOk()
            ->assertJsonPath('data.teacher_comments', 'Good recovery after correction.');

        $this->getJson("/api/v1/student-progress-records/{$otherRecord->id}")
            ->assertForbidden();

        $this->postJson('/api/v1/student-progress-records', [
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_LISTENING,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_progress_summary_returns_latest_growth_snapshot_for_student(): void
    {
        Sanctum::actingAs($this->admin);

        $this->student->studentProfile->update([
            'english_level' => 'A2.1',
            'current_level' => 'A2.2',
        ]);

        $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_GRAMMAR,
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_GRAMMAR => 'Uses past tense with support.',
            ],
            'speaking_confidence_rating' => StudentProgressRecord::RATING_DEVELOPING,
            'vocabulary_progress' => 'Starting to use travel vocabulary.',
            'grammar_development' => 'Can form simple past sentences.',
            'pronunciation_progress' => 'Needs support with word stress.',
            'lesson_completion_count' => 4,
            'teacher_comments' => 'Good start on accuracy goals.',
            'milestone_achievements' => ['Completed first grammar checkpoint.'],
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_MAINTAINED,
            'goals_completed' => ['Use past tense in short answers.'],
            'goals_in_progress' => ['Speak for two minutes.'],
            'recorded_at' => '2026-06-01 10:00:00',
        ]);
        $latestSpeaking = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_SPEAKING => 'Can sustain a two-minute guided conversation.',
            ],
            'speaking_confidence_rating' => StudentProgressRecord::RATING_CONFIDENT,
            'vocabulary_progress' => 'Uses travel vocabulary without prompts.',
            'grammar_development' => 'Uses past tense more consistently.',
            'pronunciation_progress' => 'Clearer stress on common travel phrases.',
            'lesson_completion_count' => 7,
            'teacher_comments' => 'Confidence improved during role play.',
            'milestone_achievements' => ['Completed first role play.'],
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_UP,
            'goals_completed' => ['Speak for two minutes.'],
            'goals_in_progress' => ['Answer follow-up questions.'],
            'recorded_at' => '2026-06-15 10:00:00',
        ]);

        $this->getJson("/api/v1/students/{$this->student->id}/progress-summary")
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->student->id)
            ->assertJsonPath('data.current_level', 'A2.2')
            ->assertJsonPath('data.previous_level', 'A2.1')
            ->assertJsonPath('data.latest_progress_summary_per_skill_area.speaking.record_id', $latestSpeaking->id)
            ->assertJsonPath('data.latest_progress_summary_per_skill_area.speaking.summary', 'Can sustain a two-minute guided conversation.')
            ->assertJsonPath('data.speaking_confidence_rating', StudentProgressRecord::RATING_CONFIDENT)
            ->assertJsonPath('data.vocabulary_progress', 'Uses travel vocabulary without prompts.')
            ->assertJsonPath('data.grammar_development', 'Uses past tense more consistently.')
            ->assertJsonPath('data.pronunciation_progress', 'Clearer stress on common travel phrases.')
            ->assertJsonPath('data.lesson_completion_count', 7)
            ->assertJsonPath('data.completed_goals_count', 2)
            ->assertJsonPath('data.in_progress_goals_count', 1)
            ->assertJsonCount(2, 'data.milestone_achievements')
            ->assertJsonPath('data.level_movement_history.0.level_movement', StudentProgressRecord::LEVEL_MOVEMENT_UP);
    }

    public function test_progress_timeline_returns_ordered_records_with_teacher_comments(): void
    {
        Sanctum::actingAs($this->teacher);

        $older = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
            'teacher_comments' => 'Needs prompts to expand answers.',
            'lesson_completion_count' => 3,
            'recorded_at' => '2026-06-01 10:00:00',
        ]);
        $newer = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
            'teacher_comments' => 'Expanded answers with fewer prompts.',
            'lesson_completion_count' => 5,
            'recorded_at' => '2026-06-08 10:00:00',
        ]);
        $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_GRAMMAR,
            'teacher_comments' => 'Separate grammar checkpoint.',
            'recorded_at' => '2026-06-03 10:00:00',
        ]);

        $this->getJson("/api/v1/students/{$this->student->id}/progress-timeline?skill_area=".StudentProgressRecord::SKILL_SPEAKING)
            ->assertOk()
            ->assertJsonCount(2, 'data.records')
            ->assertJsonPath('data.records.0.id', $older->id)
            ->assertJsonPath('data.records.0.teacher_comment.comment', 'Needs prompts to expand answers.')
            ->assertJsonPath('data.records.0.metrics.lesson_completion_count', 3)
            ->assertJsonPath('data.records.1.id', $newer->id)
            ->assertJsonPath('data.records.1.teacher_comment.comment', 'Expanded answers with fewer prompts.')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.filters.skill_area', StudentProgressRecord::SKILL_SPEAKING);
    }

    public function test_teacher_cannot_view_progress_summary_for_unassigned_student(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/v1/students/{$this->otherStudent->id}/progress-summary")
            ->assertForbidden();
    }

    public function test_student_can_view_own_progress_timeline_only(): void
    {
        Sanctum::actingAs($this->student);

        $record = $this->createProgressRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'teacher_comments' => 'Student-visible growth note.',
            'recorded_at' => '2026-06-01 10:00:00',
        ]);

        $this->getJson("/api/v1/students/{$this->student->id}/progress-timeline")
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->student->id)
            ->assertJsonPath('data.records.0.id', $record->id)
            ->assertJsonPath('data.records.0.teacher_comment.comment', 'Student-visible growth note.');

        $this->getJson("/api/v1/students/{$this->otherStudent->id}/progress-timeline")
            ->assertForbidden();
    }

    public function test_payload_validation_rejects_invalid_enums_and_ranges(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/student-progress-records', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'skill_area' => 'invalid_skill',
            'speaking_confidence_rating' => 'excellent',
            'lesson_completion_count' => -1,
            'level_movement' => 'jumped',
            'progress_status' => 'done',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'skill_area',
                'speaking_confidence_rating',
                'lesson_completion_count',
                'level_movement',
                'progress_status',
            ]);
    }

    public function test_invalid_student_and_teacher_users_return_validation_errors(): void
    {
        Sanctum::actingAs($this->admin);

        $nonStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $nonStudent->assignRole('teacher');
        $nonTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $nonTeacher->assignRole('student');

        $this->postJson('/api/v1/student-progress-records', [
            'student_id' => 999999,
            'teacher_id' => 999998,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id', 'teacher_id']);

        $this->postJson('/api/v1/student-progress-records', [
            'student_id' => $nonStudent->id,
            'teacher_id' => $nonTeacher->id,
            'skill_area' => StudentProgressRecord::SKILL_SPEAKING,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id', 'teacher_id']);
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
            'progress_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            ...$overrides,
        ]);
    }
}
