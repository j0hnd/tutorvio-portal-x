<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Notifications\SystemNotificationEmail;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_teacher_can_create_homework_for_managed_student_and_own_lesson(): void
    {
        NotificationFacade::fake();

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

        $notification = Notification::query()
            ->where('type', Notification::TYPE_HOMEWORK_REMINDER)
            ->where('metadata->homework_id', Homework::firstOrFail()->id)
            ->firstOrFail();

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $this->student->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $this->student->id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
            'delivery_status' => NotificationRecipient::STATUS_SENT,
        ]);
        NotificationFacade::assertSentTo($this->student, SystemNotificationEmail::class);
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

    public function test_admin_can_manage_all_homework(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        Sanctum::actingAs($this->admin);

        $homework = $this->createHomework($this->student, $this->teacher, Homework::STATUS_ASSIGNED, '2026-06-20', '2026-06-01 09:00:00');
        $otherHomework = $this->createHomework($this->otherStudent, $this->otherTeacher, Homework::STATUS_ASSIGNED, '2026-06-21', '2026-06-01 10:00:00');

        $this->getJson('/api/v1/homeworks?per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $homework->id)
            ->assertJsonPath('data.1.id', $otherHomework->id);

        $this->patchJson("/api/v1/homeworks/{$otherHomework->id}/progress", [
            'status' => Homework::STATUS_COMPLETED,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $otherHomework->id)
            ->assertJsonPath('data.status', Homework::STATUS_COMPLETED);

        $this->patchJson("/api/v1/homeworks/{$otherHomework->id}/review", [
            'teacher_feedback' => 'Strong summary and clear examples.',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $otherHomework->id)
            ->assertJsonPath('data.status', Homework::STATUS_REVIEWED)
            ->assertJsonPath('data.teacher_feedback', 'Strong summary and clear examples.');
    }

    public function test_student_can_only_view_their_own_homework(): void
    {
        Sanctum::actingAs($this->student);

        $homework = $this->createHomework($this->student, $this->teacher, Homework::STATUS_ASSIGNED, '2026-06-20', '2026-06-01 09:00:00');
        $otherHomework = $this->createHomework($this->otherStudent, $this->otherTeacher, Homework::STATUS_ASSIGNED, '2026-06-21', '2026-06-01 10:00:00');

        $this->getJson('/api/v1/homeworks?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $homework->id)
            ->assertJsonPath('data.0.student_id', $this->student->id);

        $this->getJson("/api/v1/homeworks/{$homework->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $homework->id);

        $this->getJson("/api/v1/homeworks/{$otherHomework->id}")
            ->assertForbidden();
    }

    public function test_student_can_mark_their_own_homework_in_progress_and_completed(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        Sanctum::actingAs($this->student);

        $homework = $this->createHomework($this->student, $this->teacher, Homework::STATUS_ASSIGNED, '2026-06-20', '2026-06-01 09:00:00');

        $this->patchJson("/api/v1/homeworks/{$homework->id}/progress", [
            'status' => Homework::STATUS_IN_PROGRESS,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Homework::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.completed_at', null);

        $this->patchJson("/api/v1/homeworks/{$homework->id}/progress", [
            'status' => Homework::STATUS_COMPLETED,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Homework::STATUS_COMPLETED);

        $this->assertDatabaseHas('homeworks', [
            'id' => $homework->id,
            'status' => Homework::STATUS_COMPLETED,
            'completed_at' => '2026-06-15 12:00:00',
        ]);
    }

    public function test_student_cannot_review_homework(): void
    {
        Sanctum::actingAs($this->student);

        $homework = $this->createHomework($this->student, $this->teacher, Homework::STATUS_COMPLETED, '2026-06-20', '2026-06-01 09:00:00');

        $this->patchJson("/api/v1/homeworks/{$homework->id}/review", [
            'teacher_feedback' => 'Reviewed.',
        ])
            ->assertForbidden();
    }

    public function test_teacher_can_review_homework_for_assigned_students(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        Sanctum::actingAs($this->teacher);

        $homework = $this->createHomework($this->student, $this->teacher, Homework::STATUS_COMPLETED, '2026-06-20', '2026-06-01 09:00:00');

        $this->patchJson("/api/v1/homeworks/{$homework->id}/review", [
            'teacher_feedback' => 'Good work. Review the last two examples.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Homework::STATUS_REVIEWED)
            ->assertJsonPath('data.teacher_feedback', 'Good work. Review the last two examples.');

        $this->assertDatabaseHas('homeworks', [
            'id' => $homework->id,
            'status' => Homework::STATUS_REVIEWED,
            'teacher_feedback' => 'Good work. Review the last two examples.',
            'reviewed_at' => '2026-06-15 12:00:00',
        ]);
    }

    public function test_teacher_cannot_review_homework_for_unrelated_students(): void
    {
        Sanctum::actingAs($this->teacher);

        $homework = $this->createHomework($this->otherStudent, $this->otherTeacher, Homework::STATUS_COMPLETED, '2026-06-20', '2026-06-01 09:00:00');

        $this->patchJson("/api/v1/homeworks/{$homework->id}/review", [
            'teacher_feedback' => 'Reviewed.',
        ])
            ->assertForbidden();
    }

    public function test_overdue_tracking_does_not_overwrite_completed_or_reviewed_homework(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        Sanctum::actingAs($this->admin);

        $assigned = $this->createHomework($this->student, $this->teacher, Homework::STATUS_ASSIGNED, '2026-06-01', '2026-06-01 09:00:00');
        $completed = $this->createHomework($this->student, $this->teacher, Homework::STATUS_COMPLETED, '2026-06-01', '2026-06-01 10:00:00');
        $reviewed = $this->createHomework($this->student, $this->teacher, Homework::STATUS_REVIEWED, '2026-06-01', '2026-06-01 11:00:00');

        $this->getJson('/api/v1/admin/homeworks/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.total_assigned', 3)
            ->assertJsonPath('data.summary.overdue_count', 1)
            ->assertJsonPath('data.summary.completed_count', 1)
            ->assertJsonPath('data.summary.reviewed_count', 1);

        $this->assertDatabaseHas('homeworks', ['id' => $assigned->id, 'status' => Homework::STATUS_ASSIGNED]);
        $this->assertDatabaseHas('homeworks', ['id' => $completed->id, 'status' => Homework::STATUS_COMPLETED]);
        $this->assertDatabaseHas('homeworks', ['id' => $reviewed->id, 'status' => Homework::STATUS_REVIEWED]);
    }

    public function test_homework_linked_to_lesson_and_student_is_returned_correctly(): void
    {
        Sanctum::actingAs($this->teacher);

        $lesson = $this->createLesson($this->student, $this->teacher);
        $matchingHomework = Homework::factory()->create([
            'lesson_id' => $lesson->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Linked lesson worksheet',
            'status' => Homework::STATUS_ASSIGNED,
        ]);
        $this->createHomework($this->student, $this->teacher, Homework::STATUS_ASSIGNED, '2026-06-20', '2026-06-01 09:00:00');

        $this->getJson('/api/v1/homeworks?lesson_id='.$lesson->id.'&student_id='.$this->student->id.'&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingHomework->id)
            ->assertJsonPath('data.0.lesson.id', $lesson->id)
            ->assertJsonPath('data.0.lesson.student_id', $this->student->id)
            ->assertJsonPath('data.0.student.id', $this->student->id)
            ->assertJsonPath('data.0.teacher.id', $this->teacher->id);
    }

    public function test_admin_can_view_homework_summary_trends_with_filters(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        Sanctum::actingAs($this->admin);

        $this->student->studentProfile()->update([
            'course' => 'General English',
            'current_level' => 'A2.2',
        ]);
        $this->otherStudent->studentProfile()->update([
            'course' => 'Business English',
            'current_level' => 'B1.1',
        ]);

        $this->createHomework($this->student, $this->teacher, Homework::STATUS_ASSIGNED, '2026-06-20', '2026-06-01 09:00:00');
        $this->createHomework($this->student, $this->teacher, Homework::STATUS_IN_PROGRESS, '2026-06-20', '2026-06-01 10:00:00');
        $this->createHomework($this->student, $this->teacher, Homework::STATUS_COMPLETED, '2026-06-12', '2026-06-02 09:00:00');
        $this->createHomework($this->student, $this->teacher, Homework::STATUS_REVIEWED, '2026-06-12', '2026-06-02 10:00:00');
        $this->createHomework($this->student, $this->teacher, Homework::STATUS_IN_PROGRESS, '2026-06-01', '2026-06-03 09:00:00');
        $this->createHomework($this->otherStudent, $this->otherTeacher, Homework::STATUS_OVERDUE, '2026-06-01', '2026-06-03 09:00:00');

        $this->getJson('/api/v1/admin/homeworks/summary?date_from=2026-06-01&date_to=2026-06-03&teacher_id='.$this->teacher->id.'&course=General%20English&level=A2.2')
            ->assertOk()
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.filters.course', 'General English')
            ->assertJsonPath('data.filters.level', 'A2.2')
            ->assertJsonPath('data.summary.total_assigned', 5)
            ->assertJsonPath('data.summary.assigned_count', 1)
            ->assertJsonPath('data.summary.in_progress_count', 2)
            ->assertJsonPath('data.summary.completed_count', 1)
            ->assertJsonPath('data.summary.reviewed_count', 1)
            ->assertJsonPath('data.summary.overdue_count', 1)
            ->assertJsonPath('data.summary.completion_rate', 0.4)
            ->assertJsonPath('data.summary.overdue_rate', 0.2)
            ->assertJsonCount(3, 'data.trends')
            ->assertJsonPath('data.trends.0.date', '2026-06-01')
            ->assertJsonPath('data.trends.0.total_assigned', 2)
            ->assertJsonPath('data.trends.2.date', '2026-06-03')
            ->assertJsonPath('data.trends.2.overdue_count', 1);

    }

    public function test_homework_summary_supports_student_and_status_filters(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createHomework($this->student, $this->teacher, Homework::STATUS_COMPLETED, '2026-06-20', '2026-06-01 09:00:00');
        $this->createHomework($this->student, $this->teacher, Homework::STATUS_IN_PROGRESS, '2026-06-20', '2026-06-01 10:00:00');
        $this->createHomework($this->otherStudent, $this->otherTeacher, Homework::STATUS_COMPLETED, '2026-06-20', '2026-06-01 11:00:00');

        $this->getJson('/api/v1/admin/homeworks/summary?student_id='.$this->student->id.'&status='.Homework::STATUS_COMPLETED)
            ->assertOk()
            ->assertJsonPath('data.summary.total_assigned', 1)
            ->assertJsonPath('data.summary.completed_count', 1)
            ->assertJsonPath('data.summary.in_progress_count', 0);
    }

    public function test_staff_homework_summary_access_requires_permission(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/homeworks/summary')
            ->assertForbidden();

        $staff->givePermissionTo('homeworks.view');

        $this->getJson('/api/v1/admin/homeworks/summary')
            ->assertOk();
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

    private function createHomework(
        User $student,
        User $teacher,
        string $status,
        string $dueDate,
        string $createdAt,
    ): Homework {
        $lesson = $this->createLesson($student, $teacher);

        return Homework::factory()->create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => $status,
            'due_date' => $dueDate,
            'completed_at' => $status === Homework::STATUS_COMPLETED ? $createdAt : null,
            'reviewed_at' => $status === Homework::STATUS_REVIEWED ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
