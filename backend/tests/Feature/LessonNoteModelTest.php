<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\LessonRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LessonNoteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_note_stores_structured_fields_and_relationships(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();
        $author = User::factory()->create();

        $lesson = Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => '2026-06-01 09:00:00',
            'end_time' => '2026-06-01 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
        ]);

        $lessonRecord = LessonRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'scheduled_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
            'completed_at' => '2026-06-01 10:05:00',
            'completed_by' => $teacher->id,
        ]);

        $submittedAt = Carbon::parse('2026-06-01 10:10:00');

        $lessonNote = LessonNote::create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'author_id' => $author->id,
            'lesson_record_id' => $lessonRecord->id,
            'lesson_objective' => 'Practice workplace introductions.',
            'topics_covered' => 'Introductions, job responsibilities, follow-up questions.',
            'vocabulary_learned' => 'deadline, colleague, onboarding',
            'grammar_focus' => 'Present simple for routines.',
            'pronunciation_issues' => 'Final consonants need more practice.',
            'student_speaking_confidence_observation' => 'More willing to answer without prompts.',
            'homework_assignment' => 'Prepare a two-minute self introduction.',
            'recommendation_for_next_lesson' => 'Review follow-up questions in role play.',
            'internal_note' => 'Keep correction direct but brief.',
            'submitted_at' => $submittedAt,
        ]);

        $this->assertDatabaseHas('lesson_notes', [
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'author_id' => $author->id,
            'lesson_record_id' => $lessonRecord->id,
            'lesson_objective' => 'Practice workplace introductions.',
            'homework_assignment' => 'Prepare a two-minute self introduction.',
            'internal_note' => 'Keep correction direct but brief.',
        ]);

        $this->assertTrue($lessonNote->submitted_at->equalTo($submittedAt));
        $this->assertTrue($lesson->lessonNote->is($lessonNote));
        $this->assertTrue($lessonRecord->lessonNote->is($lessonNote));
        $this->assertTrue($student->studentLessonNotes->first()->is($lessonNote));
        $this->assertTrue($teacher->teacherLessonNotes->first()->is($lessonNote));
        $this->assertTrue($author->authoredLessonNotes->first()->is($lessonNote));
    }

    public function test_lesson_notes_table_enforces_one_note_per_lesson_and_progress_record(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();
        $lesson = $this->createLesson($student, $teacher, '2026-06-01 09:00:00');

        LessonNote::create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->expectException(QueryException::class);

        LessonNote::create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_lesson_notes_table_enforces_one_note_per_progress_record(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();
        $lessonRecord = $this->createLessonRecord($student, $teacher);

        LessonNote::create([
            'lesson_id' => $this->createLesson($student, $teacher, '2026-06-01 09:00:00')->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'lesson_record_id' => $lessonRecord->id,
        ]);

        $this->expectException(QueryException::class);

        LessonNote::create([
            'lesson_id' => $this->createLesson($student, $teacher, '2026-06-02 09:00:00')->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'lesson_record_id' => $lessonRecord->id,
        ]);
    }

    private function createLesson(User $student, User $teacher, string $startsAt): Lesson
    {
        $start = Carbon::parse($startsAt);

        return Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => Lesson::STATUS_COMPLETED,
        ]);
    }

    private function createLessonRecord(User $student, User $teacher): LessonRecord
    {
        return LessonRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'scheduled_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
        ]);
    }
}
