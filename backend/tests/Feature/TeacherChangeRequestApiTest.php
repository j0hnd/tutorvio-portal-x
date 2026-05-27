<?php

namespace Tests\Feature;

use App\Models\TeacherChangeRequest;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherChangeRequestApiTest extends TestCase
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

        $this->createActiveAssignment($this->student, $this->teacher);
    }

    public function test_student_can_submit_and_view_own_teacher_change_request_status(): void
    {
        Sanctum::actingAs($this->student);

        $response = $this->postJson('/api/v1/teacher-change-requests', [
            'requested_reason' => 'I need a teacher with evening availability.',
            'preferred_schedule_notes' => 'Weeknights after 7 PM.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.current_teacher_id', $this->teacher->id)
            ->assertJsonPath('data.status', TeacherChangeRequest::STATUS_PENDING)
            ->assertJsonMissingPath('data.admin_notes')
            ->assertJsonMissingPath('data.reviewed_by');

        $requestId = $response->json('data.id');

        $this->getJson('/api/v1/teacher-change-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $requestId);

        $this->getJson("/api/v1/teacher-change-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.status', TeacherChangeRequest::STATUS_PENDING)
            ->assertJsonMissingPath('data.admin_notes');
    }

    public function test_student_cannot_create_request_without_active_teacher_assignment(): void
    {
        $studentWithoutTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $studentWithoutTeacher->assignRole('student');

        Sanctum::actingAs($studentWithoutTeacher);

        $this->postJson('/api/v1/teacher-change-requests', [
            'requested_reason' => 'Please assign another teacher.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_teacher_id');
    }

    public function test_admin_can_view_pending_requests_and_reject_with_internal_notes(): void
    {
        $request = TeacherChangeRequest::factory()->create([
            'student_id' => $this->student->id,
            'current_teacher_id' => $this->teacher->id,
            'requested_reason' => 'Schedule mismatch.',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/teacher-change-requests/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $request->id);

        $this->postJson("/api/v1/admin/teacher-change-requests/{$request->id}/reject", [
            'review_reason' => 'Please try the new class schedule first.',
            'admin_notes' => 'Reviewed with student support.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TeacherChangeRequest::STATUS_REJECTED)
            ->assertJsonPath('data.reviewed_by', $this->admin->id)
            ->assertJsonPath('data.admin_notes', 'Reviewed with student support.');

        $this->assertDatabaseHas('teacher_change_requests', [
            'id' => $request->id,
            'status' => TeacherChangeRequest::STATUS_REJECTED,
            'reviewed_by' => $this->admin->id,
            'review_reason' => 'Please try the new class schedule first.',
            'admin_notes' => 'Reviewed with student support.',
        ]);
    }

    public function test_admin_can_approve_and_optionally_reassign_to_selected_teacher(): void
    {
        $request = TeacherChangeRequest::factory()->create([
            'student_id' => $this->student->id,
            'current_teacher_id' => $this->teacher->id,
            'requested_reason' => 'Need a better schedule fit.',
        ]);

        $newTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $newTeacher->assignRole('teacher');

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/admin/teacher-change-requests/{$request->id}/approve", [
            'new_teacher_id' => $newTeacher->id,
            'reassign' => true,
            'review_reason' => 'Approved for evening availability.',
            'admin_notes' => 'Moved to the new teacher immediately.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TeacherChangeRequest::STATUS_APPROVED)
            ->assertJsonPath('data.approved_teacher_id', $newTeacher->id);

        $this->assertDatabaseHas('teacher_student_assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => TeacherStudentAssignment::STATUS_REASSIGNED,
            'active_student_id' => null,
        ]);

        $this->assertDatabaseHas('teacher_student_assignments', [
            'student_id' => $this->student->id,
            'teacher_id' => $newTeacher->id,
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'active_student_id' => $this->student->id,
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $this->student->id,
            'assigned_teacher_id' => $newTeacher->id,
        ]);
    }

    public function test_staff_requires_permissions_and_teacher_cannot_view_requests(): void
    {
        $request = TeacherChangeRequest::factory()->create([
            'student_id' => $this->student->id,
            'current_teacher_id' => $this->teacher->id,
        ]);

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/teacher-change-requests/pending')
            ->assertForbidden();

        $staff->givePermissionTo('teacher_change_requests.view');

        $this->getJson('/api/v1/admin/teacher-change-requests/pending')
            ->assertOk();

        $this->postJson("/api/v1/admin/teacher-change-requests/{$request->id}/reject", [
            'review_reason' => 'Not approved.',
        ])
            ->assertForbidden();

        $staff->givePermissionTo('teacher_change_requests.manage');

        $this->postJson("/api/v1/admin/teacher-change-requests/{$request->id}/reject", [
            'review_reason' => 'Not approved.',
        ])
            ->assertOk();

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/admin/teacher-change-requests/pending')
            ->assertForbidden();

        $this->getJson("/api/v1/teacher-change-requests/{$request->id}")
            ->assertForbidden();
    }

    public function test_student_can_cancel_only_own_pending_request(): void
    {
        $request = TeacherChangeRequest::factory()->create([
            'student_id' => $this->student->id,
            'current_teacher_id' => $this->teacher->id,
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/teacher-change-requests/{$request->id}/cancel", [
            'review_reason' => 'I no longer need a different teacher.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TeacherChangeRequest::STATUS_CANCELLED);

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/v1/teacher-change-requests/{$request->id}")
            ->assertForbidden();
    }

    private function createActiveAssignment(User $student, User $teacher): void
    {
        TeacherStudentAssignment::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'active_student_id' => $student->id,
        ]);
    }
}
