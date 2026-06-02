<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeworkModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_homework_stores_required_fields_and_relationships(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();

        $lesson = Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => '2026-06-10 09:00:00',
            'end_time' => '2026-06-10 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
        ]);

        $resource = LearningResource::create([
            'title' => 'Homework worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'created_by' => $teacher->id,
        ]);

        $completedAt = Carbon::parse('2026-06-12 11:30:00');
        $reviewedAt = Carbon::parse('2026-06-13 08:15:00');

        $homework = Homework::create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'title' => 'Write a short business introduction',
            'instructions' => 'Submit a 150-word self-introduction with role and responsibilities.',
            'due_date' => '2026-06-12',
            'status' => Homework::STATUS_REVIEWED,
            'teacher_feedback' => 'Good structure. Improve article usage in the second paragraph.',
            'completed_at' => $completedAt,
            'reviewed_at' => $reviewedAt,
            'attachment_links' => [
                'https://example.com/homework-guidelines',
            ],
        ]);

        $homework->learningResources()->attach($resource->id, [
            'assigned_by' => $teacher->id,
            'assigned_at' => '2026-06-10 10:05:00',
        ]);

        $this->assertDatabaseHas('homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'title' => 'Write a short business introduction',
            'status' => Homework::STATUS_REVIEWED,
        ]);
        $this->assertDatabaseHas('homework_learning_resource', [
            'homework_id' => $homework->id,
            'learning_resource_id' => $resource->id,
            'assigned_by' => $teacher->id,
        ]);

        $this->assertTrue($homework->lesson->is($lesson));
        $this->assertTrue($homework->student->is($student));
        $this->assertTrue($homework->teacher->is($teacher));
        $this->assertTrue($lesson->homeworks->first()->is($homework));
        $this->assertTrue($student->studentHomeworks->first()->is($homework));
        $this->assertTrue($teacher->teacherHomeworks->first()->is($homework));
        $this->assertTrue($homework->learningResources->first()->is($resource));
        $this->assertTrue($resource->assignedHomeworks->first()->is($homework));
        $this->assertSame(['https://example.com/homework-guidelines'], $homework->attachment_links);
        $this->assertTrue($homework->completed_at->equalTo($completedAt));
        $this->assertTrue($homework->reviewed_at->equalTo($reviewedAt));
    }

    public function test_homework_statuses_constant_matches_supported_statuses(): void
    {
        $this->assertSame([
            Homework::STATUS_ASSIGNED,
            Homework::STATUS_IN_PROGRESS,
            Homework::STATUS_COMPLETED,
            Homework::STATUS_REVIEWED,
            Homework::STATUS_OVERDUE,
        ], Homework::STATUSES);
    }
}
