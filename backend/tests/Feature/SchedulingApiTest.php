<?php

namespace Tests\Feature;

use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\Holiday;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SchedulingApiTest extends TestCase
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
    }

    public function test_admin_can_create_timezone_aware_class_schedule(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        $response = $this->postJson('/api/v1/scheduling/class-schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'General English',
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', ClassSchedule::STATUS_SCHEDULED)
            ->assertJsonPath('data.timezone', 'Asia/Manila');

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);
    }

    public function test_schedule_creation_rejects_unavailable_teacher_time(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherUnavailableDate::create([
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:30:00',
            'ends_at' => '2026-06-01 02:30:00',
            'reason' => 'Training',
        ]);

        $this->postJson('/api/v1/scheduling/class-schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_student_can_only_see_their_own_schedules(): void
    {
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-02 02:00:00',
            'ends_at' => '2026-06-02 03:00:00',
        ]);

        Sanctum::actingAs($this->student);

        $response = $this->getJson('/api/v1/scheduling/class-schedules');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $this->student->id);
    }

    public function test_holidays_block_schedule_creation(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        Holiday::create([
            'name' => 'Foundation Day',
            'date' => '2026-06-01',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/scheduling/class-schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_teacher_can_manage_only_their_own_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        Sanctum::actingAs($this->teacher);

        $response = $this->postJson('/api/v1/scheduling/teacher-availabilities', [
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $this->teacher->id);

        $this->postJson('/api/v1/scheduling/teacher-availabilities', [
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'timezone' => 'Asia/Manila',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');

        $this->patchJson('/api/v1/scheduling/teacher-availabilities/'.$response->json('data.id'), [
            'end_time' => '13:00',
        ])->assertOk();

        $this->deleteJson('/api/v1/scheduling/teacher-availabilities/'.$response->json('data.id'))
            ->assertNoContent();
    }

    public function test_admin_can_view_all_teacher_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/scheduling/teacher-availabilities')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_student_can_only_view_assigned_teacher_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/scheduling/teacher-availabilities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $this->teacher->id);
    }

    public function test_overlapping_teacher_availability_is_rejected(): void
    {
        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/scheduling/teacher-availabilities', [
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '11:00',
            'end_time' => '13:00',
            'timezone' => 'Asia/Manila',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_teacher_cannot_update_or_delete_booked_availability_slot(): void
    {
        $availability = TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->patchJson('/api/v1/scheduling/teacher-availabilities/'.$availability->id, [
            'end_time' => '13:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('availability');

        $this->deleteJson('/api/v1/scheduling/teacher-availabilities/'.$availability->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('availability');
    }

    public function test_teacher_can_create_timezone_aware_unavailable_date(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/scheduling/teacher-unavailable-dates', [
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 12:00:00',
            'timezone' => 'Asia/Manila',
            'reason' => 'Training',
        ])
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $this->teacher->id);

        $this->assertDatabaseHas('teacher_unavailable_dates', [
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 04:00:00',
            'timezone' => 'Asia/Manila',
        ]);
    }

    public function test_admin_calendar_view_returns_all_scheduling_blocks_for_requested_month(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $otherTeacher->id,
            'status' => ClassSchedule::STATUS_PENDING_CONFIRMATION,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-02 01:00:00',
            'ends_at' => '2026-06-02 02:00:00',
        ]);

        TeacherUnavailableDate::create([
            'teacher_id' => $otherTeacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-03 01:00:00',
            'ends_at' => '2026-06-03 02:00:00',
            'reason' => 'Training',
        ]);

        Holiday::create([
            'name' => 'Foundation Day',
            'date' => '2026-06-04',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/scheduling/calendar?view=month&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonPath('data.view', 'month')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.booked_lessons.0.status', ClassSchedule::STATUS_PENDING_CONFIRMATION)
            ->assertJsonCount(5, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons')
            ->assertJsonCount(1, 'data.unavailable_dates')
            ->assertJsonCount(1, 'data.holiday_blocks');
    }

    public function test_teacher_calendar_is_scoped_to_own_schedule_and_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:00:00',
            'ends_at' => '2026-06-01 02:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $otherTeacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 05:00:00',
            'ends_at' => '2026-06-01 06:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/scheduling/calendar?view=day&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonCount(1, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons')
            ->assertJsonPath('data.availability.0.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.booked_lessons.0.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.booked_lessons.0.starts_at', '2026-06-01T09:00:00+08:00');
    }

    public function test_student_calendar_only_includes_own_classes_and_assigned_teacher_availability(): void
    {
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherStudent->assignRole('student');

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_COMPLETED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:00:00',
            'ends_at' => '2026-06-01 02:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 03:00:00',
            'ends_at' => '2026-06-01 04:00:00',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/scheduling/calendar?view=week&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonCount(1, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons')
            ->assertJsonPath('data.availability.0.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.booked_lessons.0.student.id', $this->student->id)
            ->assertJsonPath('data.booked_lessons.0.status', ClassSchedule::STATUS_COMPLETED);
    }
}
