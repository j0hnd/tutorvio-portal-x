<?php

namespace Tests\Feature;

use App\Models\Scheduling\TeacherAvailability;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminTeacherStudentAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_assign_and_reassign_teacher_without_losing_history(): void
    {
        $firstResponse = $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'assigned_at' => '2026-06-01 09:00:00',
            'reason' => 'Initial placement',
            'notes' => 'Matched for level.',
        ]);

        $firstResponse
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.status', TeacherStudentAssignment::STATUS_ACTIVE)
            ->assertJsonPath('data.reason', 'Initial placement');

        $firstAssignmentId = $firstResponse->json('data.id');

        $secondTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $secondTeacher->assignRole('teacher');

        $secondResponse = $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $secondTeacher->id,
            'assigned_at' => '2026-06-15 10:00:00',
            'reason' => 'Schedule change',
            'previous_assignment_notes' => 'Reassigned because the first teacher changed availability.',
        ]);

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $secondTeacher->id)
            ->assertJsonPath('data.status', TeacherStudentAssignment::STATUS_ACTIVE);

        $this->assertDatabaseHas('teacher_student_assignments', [
            'id' => $firstAssignmentId,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => TeacherStudentAssignment::STATUS_REASSIGNED,
            'active_student_id' => null,
        ]);

        $this->assertDatabaseHas('teacher_student_assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $secondTeacher->id,
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'active_student_id' => $this->student->id,
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $this->student->id,
            'assigned_teacher_id' => $secondTeacher->id,
        ]);

        $this->getJson('/api/v1/admin/teacher-student-assignments?student_id='.$this->student->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/admin/teacher-student-assignments?student_id='.$this->student->id.'&active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $secondTeacher->id);
    }

    public function test_assignment_rejects_inactive_or_wrong_role_users(): void
    {
        $inactiveTeacher = User::factory()->create(['status' => User::STATUS_INACTIVE]);
        $inactiveTeacher->assignRole('teacher');

        $nonStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $nonStudent->assignRole('staff');

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $inactiveTeacher->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $nonStudent->id,
            'teacher_id' => $this->teacher->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_staff_requires_permission_and_students_teachers_cannot_manage_assignments(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/teacher-student-assignments')
            ->assertForbidden();

        $staff->givePermissionTo('teacher_assignments.view', 'teacher_assignments.manage');

        $assignmentResponse = $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $assignmentResponse->assertCreated();

        $assignmentId = $assignmentResponse->json('data.id');

        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ])
            ->assertForbidden();

        Sanctum::actingAs($this->student);

        $this->patchJson("/api/v1/admin/teacher-student-assignments/{$assignmentId}", [
            'status' => TeacherStudentAssignment::STATUS_ENDED,
        ])
            ->assertForbidden();
    }

    public function test_admin_can_discover_available_teachers_for_student_assignment(): void
    {
        $this->student->studentProfile()->create([
            'course' => 'General English',
            'class_type' => 'regular',
        ]);

        $availableTeacher = $this->createTeacherOption('Available Teacher', [
            'class_load' => 3,
            'internal_status' => 'available',
            'specialization' => 'General English regular lessons',
        ], hasAvailability: true);
        $this->createActiveAssignmentForTeacher($availableTeacher);

        $fullTeacher = $this->createTeacherOption('Full Teacher', [
            'class_load' => 1,
            'internal_status' => 'available',
            'specialization' => 'General English regular lessons',
        ], hasAvailability: true);
        $this->createActiveAssignmentForTeacher($fullTeacher);

        $this->createTeacherOption('No Schedule Teacher', [
            'class_load' => 3,
            'internal_status' => 'available',
            'specialization' => 'General English regular lessons',
        ]);

        $this->createTeacherOption('Unavailable Teacher', [
            'class_load' => 3,
            'internal_status' => 'unavailable',
            'specialization' => 'General English regular lessons',
        ], hasAvailability: true);

        $this->createTeacherOption('Incompatible Teacher', [
            'class_load' => 3,
            'internal_status' => 'available',
            'specialization' => 'IELTS trial prep',
        ], hasAvailability: true);

        $inactiveTeacher = $this->createTeacherOption('Inactive Teacher', [
            'class_load' => 3,
            'internal_status' => 'available',
            'specialization' => 'General English regular lessons',
        ], hasAvailability: true);
        $inactiveTeacher->update(['status' => User::STATUS_INACTIVE]);

        $this->getJson("/api/v1/admin/students/{$this->student->id}/available-teachers?from=2026-06-01&to=2026-06-01&timezone=Asia/Manila&slot_minutes=60")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $availableTeacher->id)
            ->assertJsonPath('data.0.reasons.has_capacity', true)
            ->assertJsonPath('data.0.reasons.has_open_schedule', true)
            ->assertJsonPath('data.0.reasons.compatible_lesson_type', true)
            ->assertJsonPath('data.0.reasons.available_slots', 3)
            ->assertJsonPath('data.0.reasons.current_active_students', 1)
            ->assertJsonPath('data.0.reasons.max_capacity', 3)
            ->assertJsonPath('data.0.available_capacity', 2)
            ->assertJsonPath('data.0.workload_status', 'available');
    }

    public function test_available_teacher_discovery_requires_admin_or_staff_permission(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/admin/students/{$this->student->id}/available-teachers")
            ->assertForbidden();

        $staff->givePermissionTo('teacher_assignments.view');

        $this->getJson("/api/v1/admin/students/{$this->student->id}/available-teachers")
            ->assertOk();

        Sanctum::actingAs($this->student);

        $this->getJson("/api/v1/admin/students/{$this->student->id}/available-teachers")
            ->assertForbidden();
    }

    public function test_assignment_can_be_ended_and_profile_active_teacher_is_cleared(): void
    {
        $assignmentId = $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ])->json('data.id');

        $this->patchJson("/api/v1/admin/teacher-student-assignments/{$assignmentId}", [
            'status' => TeacherStudentAssignment::STATUS_ENDED,
            'reason' => 'Completed package',
            'notes' => 'No current teacher.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TeacherStudentAssignment::STATUS_ENDED)
            ->assertJsonPath('data.reason', 'Completed package');

        $this->assertDatabaseHas('teacher_student_assignments', [
            'id' => $assignmentId,
            'status' => TeacherStudentAssignment::STATUS_ENDED,
            'active_student_id' => null,
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $this->student->id,
            'assigned_teacher_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function createTeacherOption(string $name, array $profile, bool $hasAvailability = false): User
    {
        $teacher = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'timezone' => 'Asia/Manila',
        ]);
        $teacher->assignRole('teacher');
        $teacher->teacherProfile()->create($profile);

        if ($hasAvailability) {
            TeacherAvailability::create([
                'teacher_id' => $teacher->id,
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '12:00',
                'timezone' => 'Asia/Manila',
            ]);
        }

        return $teacher;
    }

    private function createActiveAssignmentForTeacher(User $teacher): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        TeacherStudentAssignment::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 00:00:00',
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'active_student_id' => $student->id,
        ]);
    }

    public function test_dedicated_assignment_end_and_history_endpoints_preserve_current_and_past_assignments(): void
    {
        $firstResponse = $this->postJson("/api/v1/admin/students/{$this->student->id}/teacher-assignment", [
            'teacher_id' => $this->teacher->id,
            'reason' => 'Initial match',
        ]);

        $firstResponse->assertCreated();

        $secondTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $secondTeacher->assignRole('teacher');

        $this->postJson("/api/v1/admin/students/{$this->student->id}/teacher-assignment/reassign", [
            'teacher_id' => $secondTeacher->id,
            'reason' => 'Better schedule fit',
            'notes' => 'Moved to evenings.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $secondTeacher->id);

        $this->getJson("/api/v1/students/{$this->student->id}/assigned-teacher")
            ->assertOk()
            ->assertJsonPath('data.teacher_id', $secondTeacher->id);

        $this->getJson("/api/v1/students/{$this->student->id}/teacher-assignment-history")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/teachers/{$secondTeacher->id}/assigned-students")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $this->student->id);

        $this->getJson("/api/v1/teachers/{$this->teacher->id}/student-assignment-history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', TeacherStudentAssignment::STATUS_REASSIGNED);

        $this->deleteJson("/api/v1/admin/students/{$this->student->id}/teacher-assignment", [
            'reason' => 'Program complete',
            'notes' => 'No replacement needed.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TeacherStudentAssignment::STATUS_ENDED);

        $this->getJson("/api/v1/students/{$this->student->id}/assigned-teacher")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->getJson("/api/v1/students/{$this->student->id}/teacher-assignment-history")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_teacher_and_student_views_are_limited_to_their_own_assignments(): void
    {
        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ])->assertCreated();

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/v1/teachers/{$this->teacher->id}/assigned-students")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/teachers/{$otherTeacher->id}/assigned-students")
            ->assertForbidden();

        Sanctum::actingAs($this->student);

        $this->getJson("/api/v1/students/{$this->student->id}/assigned-teacher")
            ->assertOk()
            ->assertJsonPath('data.teacher_id', $this->teacher->id);

        $this->getJson("/api/v1/students/{$otherStudent->id}/assigned-teacher")
            ->assertForbidden();
    }

    public function test_repeating_same_assignment_is_idempotent_but_changed_duplicate_payload_is_rejected(): void
    {
        $firstResponse = $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'assigned_at' => '2026-06-01 09:00:00',
            'reason' => 'Initial placement',
        ]);

        $firstResponse->assertCreated();

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'assigned_at' => '2026-06-01 09:00:00',
            'reason' => 'Initial placement',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));

        $this->assertDatabaseCount('teacher_student_assignments', 1);

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'reason' => 'Changed reason',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    public function test_assignment_rejects_unavailable_teacher_and_teacher_over_capacity(): void
    {
        $unavailableTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $unavailableTeacher->assignRole('teacher');
        $unavailableTeacher->teacherProfile()->create(['internal_status' => 'unavailable']);

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $unavailableTeacher->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');

        $capacityTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $capacityTeacher->assignRole('teacher');
        $capacityTeacher->teacherProfile()->create([
            'internal_status' => 'available',
            'class_load' => 1,
        ]);

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $otherStudent->id,
            'teacher_id' => $capacityTeacher->id,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/teacher-student-assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $capacityTeacher->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }
}
