<?php

namespace Tests\Feature;

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
}
