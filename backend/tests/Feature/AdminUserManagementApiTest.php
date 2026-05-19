<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_create_student(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Student One',
            'email' => 'student-one@example.com',
            'role' => 'student',
            'phone' => '+15551230001',
            'timezone' => 'America/New_York',
            'status' => User::STATUS_ACTIVE,
            'student_profile' => [
                'english_level' => 'B1',
                'course' => 'General English',
                'assigned_teacher_id' => $teacher->id,
                'class_type' => 'one_on_one',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'student-one@example.com')
            ->assertJsonPath('data.roles.0', 'student')
            ->assertJsonPath('data.student_profile.english_level', 'B1');

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $response->json('data.id'),
            'assigned_teacher_id' => $teacher->id,
        ]);
    }

    public function test_admin_can_create_teacher(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'Teacher One',
            'email' => 'teacher-one@example.com',
            'role' => 'teacher',
            'teacher_profile' => [
                'specialization' => 'Business English',
                'teaching_availability' => [
                    'monday' => ['09:00-12:00'],
                ],
                'internal_status' => 'available',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.roles.0', 'teacher')
            ->assertJsonPath('data.teacher_profile.specialization', 'Business English');

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => $response->json('data.id'),
            'internal_status' => 'available',
        ]);
    }

    public function test_admin_can_create_admin_and_staff(): void
    {
        $adminResponse = $this->postJson('/api/v1/users', [
            'name' => 'Admin Two',
            'email' => 'admin-two@example.com',
            'role' => 'admin',
        ]);

        $staffResponse = $this->postJson('/api/v1/users', [
            'name' => 'Staff One',
            'email' => 'staff-one@example.com',
            'role' => 'staff',
            'permissions' => ['users.view', 'users.update'],
            'staff_profile' => [
                'department' => 'Operations',
                'access_limitations' => 'Scheduling only',
            ],
        ]);

        $adminResponse->assertCreated()->assertJsonPath('data.roles.0', 'admin');
        $staffResponse
            ->assertCreated()
            ->assertJsonPath('data.roles.0', 'staff')
            ->assertJsonPath('data.staff_profile.department', 'Operations');

        $staff = User::findOrFail($staffResponse->json('data.id'));
        $this->assertTrue($staff->hasDirectPermission('users.update'));
    }

    public function test_admin_can_edit_user(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_INVITED]);
        $student->assignRole('student');
        $student->studentProfile()->create(['english_level' => 'A2']);

        $response = $this->patchJson("/api/v1/users/{$student->id}", [
            'name' => 'Updated Student',
            'phone' => '+15551239999',
            'timezone' => 'Asia/Manila',
            'student_profile' => [
                'english_level' => 'B2',
                'goals' => 'Improve fluency',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Student')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.student_profile.english_level', 'B2');

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'goals' => 'Improve fluency',
        ]);
    }

    public function test_admin_can_activate_and_deactivate_user_with_status_history(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_INVITED]);
        $student->assignRole('student');

        $this->postJson("/api/v1/users/{$student->id}/activate", [
            'reason' => 'Ready for lessons',
        ])->assertOk()->assertJsonPath('data.status', User::STATUS_ACTIVE);

        $this->postJson("/api/v1/users/{$student->id}/deactivate", [
            'reason' => 'Paused subscription',
        ])->assertOk()->assertJsonPath('data.status', User::STATUS_INACTIVE);

        $history = $this->getJson("/api/v1/users/{$student->id}/status-history");

        $history
            ->assertOk()
            ->assertJsonFragment([
                'new_status' => User::STATUS_ACTIVE,
                'reason' => 'Ready for lessons',
                'changed_by' => $this->admin->id,
            ])
            ->assertJsonFragment([
                'new_status' => User::STATUS_INACTIVE,
                'reason' => 'Paused subscription',
                'changed_by' => $this->admin->id,
            ]);
    }

    public function test_admin_can_assign_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $response = $this->postJson("/api/v1/users/{$user->id}/roles", [
            'role' => 'staff',
            'permissions' => ['users.view', 'users.create'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'staff');

        $user->refresh();
        $this->assertTrue($user->hasRole('staff'));
        $this->assertTrue($user->hasDirectPermission('users.create'));
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $user->id]);
    }

    public function test_student_is_blocked_from_user_management(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_teacher_is_blocked_from_admin_user_management(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_staff_without_permission_is_blocked(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'role' => 'student',
        ])->assertForbidden();
    }
}
