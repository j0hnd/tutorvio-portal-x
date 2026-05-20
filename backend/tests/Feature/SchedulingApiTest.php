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
}
