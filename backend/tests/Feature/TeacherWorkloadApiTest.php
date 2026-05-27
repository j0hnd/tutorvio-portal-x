<?php

namespace Tests\Feature;

use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherWorkloadApiTest extends TestCase
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
        $this->teacher->teacherProfile()->create([
            'class_load' => 3,
            'internal_status' => 'available',
        ]);

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');
    }

    public function test_admin_can_list_teacher_workload_summary(): void
    {
        $this->seedWorkloadFixture();

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/teacher-workloads?from=2026-06-01&to=2026-06-01&timezone=Asia/Manila');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.0.teacher_name', $this->teacher->name)
            ->assertJsonPath('data.0.active_student_count', 2)
            ->assertJsonPath('data.0.maximum_student_capacity', 3)
            ->assertJsonPath('data.0.available_capacity', 1)
            ->assertJsonPath('data.0.assigned_lesson_count', 1)
            ->assertJsonPath('data.0.available_slots_count', 1)
            ->assertJsonPath('data.0.current_schedule_load.available_minutes', 180)
            ->assertJsonPath('data.0.current_schedule_load.booked_minutes', 60)
            ->assertJsonPath('data.0.current_schedule_load.blocked_minutes', 60)
            ->assertJsonPath('data.0.current_schedule_load.open_minutes', 60)
            ->assertJsonPath('data.0.workload_status', 'near_capacity')
            ->assertJsonPath('data.0.unavailable_periods.0.reason', 'Training');
    }

    public function test_admin_can_view_single_teacher_workload_details(): void
    {
        $this->seedWorkloadFixture();

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/teacher-workloads/{$this->teacher->id}?from=2026-06-01&to=2026-06-01&timezone=Asia/Manila")
            ->assertOk()
            ->assertJsonPath('data.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.period.starts_at', '2026-06-01T00:00:00+08:00')
            ->assertJsonPath('data.period.ends_at', '2026-06-01T23:59:59+08:00');
    }

    public function test_teacher_can_only_view_their_own_workload(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');
        $otherTeacher->teacherProfile()->create(['class_load' => 10]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/teacher-workloads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $this->teacher->id);

        $this->getJson("/api/v1/teacher-workloads/{$otherTeacher->id}")
            ->assertForbidden();
    }

    public function test_staff_requires_permission_and_students_are_denied(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/teacher-workloads')->assertForbidden();

        $staff->givePermissionTo('teacher_workloads.view');
        $this->getJson('/api/v1/teacher-workloads')->assertOk();

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/teacher-workloads')->assertForbidden();
    }

    private function seedWorkloadFixture(): void
    {
        $students = User::factory()->count(2)->create(['status' => User::STATUS_ACTIVE]);

        foreach ($students as $student) {
            $student->assignRole('student');

            TeacherStudentAssignment::create([
                'student_id' => $student->id,
                'teacher_id' => $this->teacher->id,
                'assigned_by' => $this->admin->id,
                'assigned_at' => '2026-05-01 00:00:00',
                'status' => TeacherStudentAssignment::STATUS_ACTIVE,
                'active_student_id' => $student->id,
            ]);
        }

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $students[0]->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'class_type' => ClassSchedule::CLASS_TYPE_REGULAR,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        TeacherUnavailableDate::create([
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 03:00:00',
            'ends_at' => '2026-06-01 04:00:00',
            'reason' => 'Training',
        ]);
    }
}
