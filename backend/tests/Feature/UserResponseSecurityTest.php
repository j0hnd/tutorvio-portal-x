<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\IssueReport;
use App\Models\LearningResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserResponseSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_user_response_hides_role_status_and_private_profile_fields(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student', [
            'signed_document_path' => 'contracts/private.pdf',
            'profile_photo_path' => 'users/private-photo.jpg',
        ]);

        $student->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
            'teacher_notes' => 'Teacher-only placement note',
            'notes' => 'Administrative note',
            'internal_notes' => 'Internal escalation note',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', $student->email)
            ->assertJsonMissingPath('data.roles')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.signed_document_path')
            ->assertJsonMissingPath('data.profile_photo_path')
            ->assertJsonMissingPath('data.student_profile.teacher_notes')
            ->assertJsonMissingPath('data.student_profile.notes')
            ->assertJsonMissingPath('data.student_profile.internal_notes');
    }

    public function test_user_file_path_fields_reject_unsafe_storage_paths(): void
    {
        $admin = $this->userWithRole('admin');

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/users', [
            'name' => 'Unsafe Paths',
            'email' => 'unsafe-paths@example.test',
            'role' => 'student',
            'profile_photo_path' => '../private/avatar.jpg',
            'signed_document_path' => 'https://example.test/contract.pdf',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'profile_photo_path',
                'signed_document_path',
            ]);
    }

    public function test_user_file_path_fields_accept_expected_storage_keys(): void
    {
        $admin = $this->userWithRole('admin');

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/users', [
            'name' => 'Safe Paths',
            'email' => 'safe-paths@example.test',
            'role' => 'student',
            'profile_photo_path' => 'users/profile-photos/student-avatar.webp',
            'signed_document_path' => 'contracts/student-agreement.pdf',
        ])
            ->assertCreated()
            ->assertJsonPath('data.profile_photo_path', 'users/profile-photos/student-avatar.webp')
            ->assertJsonPath('data.signed_document_path', 'contracts/student-agreement.pdf');
    }

    public function test_student_learning_resource_response_hides_internal_metadata(): void
    {
        $student = $this->userWithRole('student');
        $admin = $this->userWithRole('admin');

        LearningResource::create([
            'title' => 'Assigned worksheet',
            'description' => 'Visible worksheet',
            'resource_type' => LearningResource::TYPE_FILE,
            'storage_disk' => 'local',
            'file_path' => 'private/worksheets/assigned.pdf',
            'original_filename' => 'assigned.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 512,
            'preview_metadata' => ['storage_path' => 'private/previews/assigned.png'],
            'visibility' => LearningResource::VISIBILITY_STUDENT_LIBRARY,
            'created_by' => $admin->id,
            'current_version_number' => 3,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/learning-resources')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Assigned worksheet')
            ->assertJsonMissingPath('data.0.preview_metadata')
            ->assertJsonMissingPath('data.0.visibility')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissing(['private/worksheets/assigned.pdf']);
    }

    public function test_student_invoice_response_hides_payment_metadata(): void
    {
        $student = $this->userWithRole('student');

        Invoice::factory()->create([
            'student_id' => $student->id,
            'payment_reference' => 'gateway-private-reference',
            'metadata' => ['gateway_payload_id' => 'pi_private'],
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->public_id}/invoices")
            ->assertOk()
            ->assertJsonMissingPath('data.0.payment_reference')
            ->assertJsonMissingPath('data.0.metadata')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissing(['gateway-private-reference'])
            ->assertJsonMissing(['pi_private']);
    }

    public function test_student_issue_report_response_hides_staff_resolution_fields(): void
    {
        $student = $this->userWithRole('student');
        $staff = $this->userWithRole('staff');

        $issue = IssueReport::create([
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'status' => IssueReport::STATUS_RESOLVED,
            'priority' => IssueReport::PRIORITY_NORMAL,
            'reporter_id' => $student->id,
            'target_user_id' => $student->id,
            'assigned_to_id' => $staff->id,
            'title' => 'Cannot join class',
            'description' => 'Student-facing description',
            'resolution_notes' => 'Staff-only resolution note',
            'resolved_at' => now(),
            'resolved_by' => $staff->id,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/issue-reports/{$issue->public_id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Cannot join class')
            ->assertJsonMissingPath('data.assigned_to_id')
            ->assertJsonMissingPath('data.resolution_notes')
            ->assertJsonMissingPath('data.resolved_by')
            ->assertJsonMissingPath('data.created_at')
            ->assertJsonMissing(['Staff-only resolution note']);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            ...$attributes,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
