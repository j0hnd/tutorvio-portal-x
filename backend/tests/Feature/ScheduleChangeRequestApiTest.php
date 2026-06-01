<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\ScheduleChangeRequest;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ScheduleChangeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

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

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);
    }

    public function test_student_can_request_own_class_schedule_change_pending_approval(): void
    {
        $schedule = $this->createClassSchedule();

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/schedule-change-requests', [
            'class_schedule_id' => $schedule->id,
            'requested_starts_at' => '2026-06-01T11:00:00+08:00',
            'requested_ends_at' => '2026-06-01T12:00:00+08:00',
            'timezone' => 'Asia/Manila',
            'reason' => 'Exam schedule conflict.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', ScheduleChangeRequest::STATUS_PENDING)
            ->assertJsonPath('data.requester_id', $this->student->public_id)
            ->assertJsonPath('data.class_schedule_id', $schedule->public_id);

        $this->assertDatabaseHas('schedule_change_requests', [
            'requester_id' => $this->student->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'class_schedule_id' => $schedule->id,
            'status' => ScheduleChangeRequest::STATUS_PENDING,
            'current_starts_at' => '2026-06-01 02:00:00',
            'requested_starts_at' => '2026-06-01 03:00:00',
        ]);

        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);
    }

    public function test_admin_can_approve_schedule_change_and_update_class_schedule(): void
    {
        $schedule = $this->createClassSchedule();
        $changeRequest = $this->createScheduleChangeRequest($schedule);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/admin/schedule-change-requests/{$changeRequest->public_id}/approve", [
            'review_notes' => 'Approved.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ScheduleChangeRequest::STATUS_APPROVED)
            ->assertJsonPath('data.reviewed_by', $this->admin->id)
            ->assertJsonPath('data.review_notes', 'Approved.');

        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'starts_at' => '2026-06-01 03:00:00',
            'ends_at' => '2026-06-01 04:00:00',
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reject_schedule_change_without_changing_class_schedule(): void
    {
        $schedule = $this->createClassSchedule();
        $changeRequest = $this->createScheduleChangeRequest($schedule);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/admin/schedule-change-requests/{$changeRequest->public_id}/reject", [
            'review_notes' => 'Teacher unavailable.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ScheduleChangeRequest::STATUS_REJECTED)
            ->assertJsonPath('data.review_notes', 'Teacher unavailable.');

        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);
    }

    public function test_schedule_change_is_auto_approved_when_setting_disables_approval(): void
    {
        PortalSetting::create([
            'key' => 'scheduling.schedule_change_requires_approval',
            'category' => 'scheduling',
            'value' => false,
            'value_type' => PortalSetting::TYPE_BOOLEAN,
            'description' => 'Whether schedule change requests require staff approval.',
            'is_public' => false,
            'updated_by' => $this->admin->id,
        ]);

        $schedule = $this->createClassSchedule();

        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/schedule-change-requests', [
            'class_schedule_id' => $schedule->id,
            'requested_starts_at' => '2026-06-01T11:00:00+08:00',
            'requested_ends_at' => '2026-06-01T12:00:00+08:00',
            'timezone' => 'Asia/Manila',
            'reason' => 'Student requested a later class.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', ScheduleChangeRequest::STATUS_APPROVED)
            ->assertJsonPath('data.reviewed_by', $this->teacher->id);

        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'starts_at' => '2026-06-01 03:00:00',
            'ends_at' => '2026-06-01 04:00:00',
            'updated_by' => $this->teacher->id,
        ]);
    }

    public function test_student_cannot_request_schedule_change_for_another_students_class(): void
    {
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $schedule = $this->createClassSchedule($otherStudent);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/schedule-change-requests', [
            'class_schedule_id' => $schedule->id,
            'requested_starts_at' => '2026-06-01T11:00:00+08:00',
            'requested_ends_at' => '2026-06-01T12:00:00+08:00',
            'timezone' => 'Asia/Manila',
            'reason' => 'Trying to move another class.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('class_schedule_id');
    }

    public function test_admin_can_view_all_schedule_change_requests(): void
    {
        $schedule = $this->createClassSchedule();
        $this->createScheduleChangeRequest($schedule);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/schedule-change-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.class_schedule_id', $schedule->public_id);
    }

    public function test_staff_schedule_change_approval_flow_depends_on_view_and_manage_permissions(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $schedule = $this->createClassSchedule();
        $changeRequest = $this->createScheduleChangeRequest($schedule);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/schedule-change-requests')
            ->assertForbidden();
        $this->postJson("/api/v1/admin/schedule-change-requests/{$changeRequest->public_id}/approve", [
            'review_notes' => 'Approved by operations.',
        ])->assertForbidden();

        $staff->givePermissionTo('schedule_change_requests.view');

        $this->getJson('/api/v1/admin/schedule-change-requests/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $changeRequest->public_id);
        $this->getJson("/api/v1/admin/schedule-change-requests/{$changeRequest->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $changeRequest->public_id);
        $this->postJson("/api/v1/admin/schedule-change-requests/{$changeRequest->public_id}/approve", [
            'review_notes' => 'Approved by operations.',
        ])->assertForbidden();

        $staff->givePermissionTo('schedule_change_requests.manage');

        $this->postJson("/api/v1/admin/schedule-change-requests/{$changeRequest->public_id}/approve", [
            'review_notes' => 'Approved by operations.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ScheduleChangeRequest::STATUS_APPROVED)
            ->assertJsonPath('data.reviewed_by', $staff->id)
            ->assertJsonPath('data.review_notes', 'Approved by operations.');

        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'starts_at' => '2026-06-01 03:00:00',
            'ends_at' => '2026-06-01 04:00:00',
            'updated_by' => $staff->id,
        ]);
    }

    private function createClassSchedule(?User $student = null): ClassSchedule
    {
        return ClassSchedule::create([
            'student_id' => ($student ?? $this->student)->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
            'teacher_blocked_until' => '2026-06-01 03:00:00',
        ]);
    }

    private function createScheduleChangeRequest(ClassSchedule $schedule): ScheduleChangeRequest
    {
        return ScheduleChangeRequest::create([
            'requester_id' => $this->student->id,
            'student_id' => $schedule->student_id,
            'teacher_id' => $schedule->teacher_id,
            'class_schedule_id' => $schedule->id,
            'current_starts_at' => $schedule->starts_at,
            'current_ends_at' => $schedule->ends_at,
            'requested_starts_at' => '2026-06-01 03:00:00',
            'requested_ends_at' => '2026-06-01 04:00:00',
            'timezone' => 'Asia/Manila',
            'reason' => 'Exam schedule conflict.',
            'status' => ScheduleChangeRequest::STATUS_PENDING,
        ]);
    }
}
