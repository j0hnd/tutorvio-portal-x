<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\Lesson;
use App\Models\MessageThread;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PublicIdApiBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config(['lessons.booking_locks.store' => 'array']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_public_id_route_binding_accepts_public_ids_and_rejects_numeric_ids(): void
    {
        Sanctum::actingAs($this->student);

        $thread = MessageThread::create([
            'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
            'status' => MessageThread::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
            'last_message_at' => now(),
        ]);

        $thread->participants()->createMany([
            ['user_id' => $this->student->id, 'participant_role' => 'student'],
            ['user_id' => $this->teacher->id, 'participant_role' => 'teacher'],
        ]);

        $this->getJson("/api/v1/message-threads/{$thread->public_id}/messages")
            ->assertOk();

        $this->getJson("/api/v1/message-threads/{$thread->id}/messages")
            ->assertNotFound();
    }

    public function test_public_id_filters_are_accepted_and_echo_public_ids_in_report_payloads(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/admin/homeworks/summary?teacher_id={$this->teacher->public_id}&student_id={$this->student->public_id}")
            ->assertOk()
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->public_id)
            ->assertJsonPath('data.filters.student_id', $this->student->public_id);
    }

    public function test_public_id_request_payloads_create_resources_with_public_id_responses(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        $scheduleResponse = $this->postJson('/api/v1/scheduling/class-schedules', [
            'student_id' => $this->student->public_id,
            'teacher_id' => $this->teacher->public_id,
            'title' => 'Public ID lesson',
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);

        $schedule = ClassSchedule::query()
            ->where('public_id', $scheduleResponse->json('data.id'))
            ->firstOrFail();

        $lesson = Lesson::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'class_schedule_id' => $schedule->id,
            'start_time' => '2026-06-01 10:00:00',
            'end_time' => '2026-06-01 11:00:00',
            'status' => Lesson::STATUS_COMPLETED,
        ]);

        $this->postJson('/api/v1/homeworks', [
            'lesson_id' => $lesson->public_id,
            'student_id' => $this->student->public_id,
            'title' => 'Public ID homework',
        ])
            ->assertCreated()
            ->assertJsonPath('data.lesson_id', $lesson->public_id)
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);

        $this->assertDatabaseHas('homeworks', [
            'lesson_id' => $lesson->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Public ID homework',
            'status' => Homework::STATUS_ASSIGNED,
        ]);
    }
}
