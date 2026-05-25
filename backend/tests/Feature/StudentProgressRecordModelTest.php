<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\StudentProgressRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentProgressRecordModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_progress_record_stores_progress_history_and_relationships(): void
    {
        $student = User::factory()->create();
        $studentProfile = StudentProfile::create([
            'user_id' => $student->id,
        ]);
        $teacher = User::factory()->create();
        $creator = User::factory()->create();
        $updater = User::factory()->create();
        $recordedAt = Carbon::parse('2026-06-15 14:30:00');

        $progressRecord = StudentProgressRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_WORKPLACE_COMMUNICATION,
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_SPEAKING => 'Can lead short status updates.',
                StudentProgressRecord::SKILL_LISTENING => 'Understands common meeting phrases.',
            ],
            'speaking_confidence_rating' => StudentProgressRecord::RATING_CONFIDENT,
            'vocabulary_progress' => 'Using more role-specific workplace vocabulary.',
            'grammar_development' => 'More accurate present perfect usage.',
            'pronunciation_progress' => 'Clearer final consonants in meetings.',
            'lesson_completion_count' => 12,
            'teacher_comments' => 'Ready for longer role-play activities.',
            'milestone_achievements' => [
                'Completed first mock client call.',
            ],
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_UP,
            'goals_completed' => [
                'Deliver a two-minute project update.',
            ],
            'goals_in_progress' => [
                'Handle clarification questions without prompts.',
            ],
            'progress_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
            'recorded_at' => $recordedAt,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);

        $this->assertDatabaseHas('student_progress_records', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'skill_area' => StudentProgressRecord::SKILL_WORKPLACE_COMMUNICATION,
            'speaking_confidence_rating' => StudentProgressRecord::RATING_CONFIDENT,
            'lesson_completion_count' => 12,
            'level_movement' => StudentProgressRecord::LEVEL_MOVEMENT_UP,
            'progress_status' => StudentProgressRecord::STATUS_IN_PROGRESS,
        ]);

        $this->assertTrue($progressRecord->student->is($student));
        $this->assertTrue($progressRecord->teacher->is($teacher));
        $this->assertTrue($progressRecord->createdBy->is($creator));
        $this->assertTrue($progressRecord->updatedBy->is($updater));
        $this->assertTrue($student->studentProgressRecords->first()->is($progressRecord));
        $this->assertTrue($studentProfile->progressRecords->first()->is($progressRecord));
        $this->assertTrue($teacher->teacherProgressRecords->first()->is($progressRecord));
        $this->assertTrue($creator->createdStudentProgressRecords->first()->is($progressRecord));
        $this->assertTrue($updater->updatedStudentProgressRecords->first()->is($progressRecord));
        $this->assertSame('Can lead short status updates.', $progressRecord->progress_summary_by_skill[StudentProgressRecord::SKILL_SPEAKING]);
        $this->assertSame(['Completed first mock client call.'], $progressRecord->milestone_achievements);
        $this->assertSame(['Deliver a two-minute project update.'], $progressRecord->goals_completed);
        $this->assertSame(['Handle clarification questions without prompts.'], $progressRecord->goals_in_progress);
        $this->assertTrue($progressRecord->recorded_at->equalTo($recordedAt));
    }

    public function test_student_progress_record_constants_match_supported_values(): void
    {
        $this->assertSame([
            StudentProgressRecord::SKILL_SPEAKING,
            StudentProgressRecord::SKILL_LISTENING,
            StudentProgressRecord::SKILL_VOCABULARY,
            StudentProgressRecord::SKILL_GRAMMAR,
            StudentProgressRecord::SKILL_PRONUNCIATION,
            StudentProgressRecord::SKILL_CONFIDENCE,
            StudentProgressRecord::SKILL_FLUENCY,
            StudentProgressRecord::SKILL_WORKPLACE_COMMUNICATION,
        ], StudentProgressRecord::SKILL_AREAS);

        $this->assertSame([
            StudentProgressRecord::RATING_NEEDS_SUPPORT,
            StudentProgressRecord::RATING_DEVELOPING,
            StudentProgressRecord::RATING_CONFIDENT,
            StudentProgressRecord::RATING_STRONG,
        ], StudentProgressRecord::RATINGS);

        $this->assertSame([
            StudentProgressRecord::STATUS_NOT_STARTED,
            StudentProgressRecord::STATUS_IN_PROGRESS,
            StudentProgressRecord::STATUS_COMPLETED,
            StudentProgressRecord::STATUS_NEEDS_SUPPORT,
        ], StudentProgressRecord::STATUSES);

        $this->assertSame([
            StudentProgressRecord::LEVEL_MOVEMENT_UP,
            StudentProgressRecord::LEVEL_MOVEMENT_MAINTAINED,
            StudentProgressRecord::LEVEL_MOVEMENT_DOWN,
            StudentProgressRecord::LEVEL_MOVEMENT_NEEDS_REVIEW,
        ], StudentProgressRecord::LEVEL_MOVEMENTS);
    }
}
