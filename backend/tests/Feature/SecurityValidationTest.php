<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecurityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_registration_rejects_access_control_and_audit_fields(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Injected Student',
            'email' => 'injected-student@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'permissions' => ['users.view'],
            'status' => User::STATUS_ACTIVE,
            'created_by' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'role',
                'permissions',
                'status',
                'created_by',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'injected-student@example.com',
        ]);
    }

    public function test_student_profile_update_rejects_internal_ownership_and_audit_fields(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'preferences' => 'Morning classes',
        ]);

        $this->actingAs($student)->patchJson('/api/v1/profile', [
            'status' => User::STATUS_SUSPENDED,
            'role' => 'admin',
            'updated_by' => $teacher->id,
            'student_profile' => [
                'preferences' => 'Evening classes',
                'assigned_teacher_id' => $teacher->id,
                'internal_notes' => 'Sensitive note',
                'teacher_notes' => 'Teacher-only note',
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'status',
                'role',
                'updated_by',
                'student_profile.assigned_teacher_id',
                'student_profile.internal_notes',
                'student_profile.teacher_notes',
            ]);

        $student->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $student->status);
        $this->assertSame('Morning classes', $student->studentProfile->preferences);
        $this->assertNull($student->studentProfile->assigned_teacher_id);
        $this->assertNull($student->studentProfile->internal_notes);
        $this->assertNull($student->studentProfile->teacher_notes);
    }

    public function test_protected_index_filters_reject_invalid_input(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->getJson('/api/v1/learning-resources?visibility=private-url&direction=sideways&per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'visibility',
                'direction',
                'per_page',
            ]);
    }
}
