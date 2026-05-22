<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HomeworkApiTest extends TestCase
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

    public function test_teacher_can_create_homework_for_managed_student_and_own_lesson(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson($this->student, $this->teacher);
        $document = $this->createDocument($this->teacher);

        $this->postJson('/api/v1/homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->student->id,
            'title' => 'Grammar Workbook Page 12',
            'instructions' => 'Finish exercises 1 to 5 and upload your answers.',
            'due_date' => '2026-06-12',
            'documents' => [$document->id],
            'links' => ['https://example.com/worksheet-guide'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.lesson_id', $lesson->id)
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.status', Homework::STATUS_ASSIGNED)
            ->assertJsonPath('data.due_date', '2026-06-12')
            ->assertJsonPath('data.documents.0.id', $document->id)
            ->assertJsonPath('data.links.0', 'https://example.com/worksheet-guide');

        $this->assertDatabaseHas('homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Grammar Workbook Page 12',
            'status' => Homework::STATUS_ASSIGNED,
        ]);

        $this->assertDatabaseHas('homework_learning_resource', [
            'learning_resource_id' => $document->id,
            'assigned_by' => $this->teacher->id,
        ]);
    }

    public function test_teacher_cannot_assign_homework_to_unmanaged_student(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson($this->otherStudent, $this->teacher);

        $this->postJson('/api/v1/homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->otherStudent->id,
            'title' => 'Listening Practice',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_admin_can_create_homework_for_any_student(): void
    {
        Sanctum::actingAs($this->admin);

        $lesson = $this->createLesson($this->otherStudent, $this->otherTeacher);

        $this->postJson('/api/v1/homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->otherStudent->id,
            'title' => 'Reading Summary',
            'instructions' => 'Summarize the article in 120 words.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.lesson_id', $lesson->id)
            ->assertJsonPath('data.student_id', $this->otherStudent->id)
            ->assertJsonPath('data.teacher_id', $this->otherTeacher->id)
            ->assertJsonPath('data.status', Homework::STATUS_ASSIGNED);
    }

    public function test_homework_requires_lesson_to_match_student(): void
    {
        Sanctum::actingAs($this->admin);

        $lesson = $this->createLesson($this->student, $this->teacher);

        $this->postJson('/api/v1/homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->otherStudent->id,
            'title' => 'Mismatch Validation',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    public function test_due_date_must_use_iso_date_format(): void
    {
        Sanctum::actingAs($this->admin);

        $lesson = $this->createLesson($this->student, $this->teacher);

        $this->postJson('/api/v1/homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->student->id,
            'title' => 'Date Validation',
            'due_date' => '06/15/2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
    }

    private function createLesson(User $student, User $teacher): Lesson
    {
        return Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => '2026-06-10 09:00:00',
            'end_time' => '2026-06-10 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
        ]);
    }

    private function createDocument(User $creator): LearningResource
    {
        return LearningResource::create([
            'title' => 'Homework Worksheet',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
            'created_by' => $creator->id,
        ]);
    }
}
