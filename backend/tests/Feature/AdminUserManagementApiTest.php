<?php

namespace Tests\Feature;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\AuditLog;
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

        $created = User::where('public_id', $response->json('data.id'))->firstOrFail();

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $created->id,
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

        $created = User::where('public_id', $response->json('data.id'))->firstOrFail();

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => $created->id,
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

        $staff = User::where('public_id', $staffResponse->json('data.id'))->firstOrFail();
        $this->assertTrue($staff->hasDirectPermission('users.update'));
    }

    public function test_admin_can_search_users_by_name_or_email_with_pagination(): void
    {
        $match = User::factory()->create([
            'name' => 'Searchable Student',
            'email' => 'searchable-student@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);
        $match->assignRole('student');

        $other = User::factory()->create([
            'name' => 'Unrelated Teacher',
            'email' => 'teacher@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);
        $other->assignRole('teacher');

        $this->getJson('/api/v1/users?search=searchable&per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->public_id)
            ->assertJsonPath('data.0.email', 'searchable-student@example.com');
    }

    public function test_admin_can_edit_user(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_INVITED]);
        $student->assignRole('student');
        $student->studentProfile()->create(['english_level' => 'A2']);

        $response = $this->patchJson("/api/v1/users/{$student->public_id}", [
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

        $this->postJson("/api/v1/users/{$student->public_id}/activate", [
            'reason' => 'Ready for lessons',
        ])->assertOk()->assertJsonPath('data.status', User::STATUS_ACTIVE);

        $this->postJson("/api/v1/users/{$student->public_id}/deactivate", [
            'reason' => 'Paused subscription',
        ])->assertOk()->assertJsonPath('data.status', User::STATUS_INACTIVE);

        $history = $this->getJson("/api/v1/users/{$student->public_id}/status-history");

        $history
            ->assertOk()
            ->assertJsonFragment([
                'new_status' => User::STATUS_ACTIVE,
                'reason' => 'Ready for lessons',
                'changed_by' => $this->admin->public_id,
            ])
            ->assertJsonFragment([
                'new_status' => User::STATUS_INACTIVE,
                'reason' => 'Paused subscription',
                'changed_by' => $this->admin->public_id,
            ]);
    }

    public function test_admin_can_assign_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        $response = $this->postJson("/api/v1/users/{$user->public_id}/roles", [
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
        $roleAudit = AuditLog::query()
            ->where('action_type', AuditActionType::ROLE_UPDATED->value)
            ->where('module', AuditModule::USERS->value)
            ->where('target_entity_id', $user->id)
            ->latest('id')
            ->first();
        $permissionAudit = AuditLog::query()
            ->where('action_type', AuditActionType::PERMISSION_UPDATED->value)
            ->where('module', AuditModule::PERMISSIONS->value)
            ->where('target_entity_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($roleAudit);
        $this->assertSame('student', $roleAudit->metadata['old_role'] ?? null);
        $this->assertSame('staff', $roleAudit->metadata['new_role'] ?? null);
        $this->assertNotNull($permissionAudit);
        $this->assertSame($user->id, $permissionAudit->metadata['affected_user_id'] ?? null);
    }

    public function test_admin_staff_access_level_change_is_audited(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->staffProfile()->create([
            'department' => 'Operations',
            'access_limitations' => 'Scheduling only',
        ]);

        $this->patchJson("/api/v1/users/{$staff->public_id}", [
            'staff_profile' => [
                'access_limitations' => 'Scheduling and billing',
            ],
        ])->assertOk();

        $accessAudit = AuditLog::query()
            ->where('action_type', AuditActionType::STAFF_ACCESS_LEVEL_CHANGED->value)
            ->where('module', AuditModule::USERS->value)
            ->where('target_entity_type', 'staff_profile')
            ->where('target_entity_id', $staff->staffProfile->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($accessAudit);
        $this->assertSame('Scheduling only', $accessAudit->metadata['old_access_level'] ?? null);
        $this->assertSame('Scheduling and billing', $accessAudit->metadata['new_access_level'] ?? null);
    }

    public function test_admin_permission_add_and_remove_are_audited(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->staffProfile()->create();

        $this->patchJson("/api/v1/users/{$staff->public_id}", [
            'permissions' => ['invoices.update'],
        ])->assertOk();

        $addedAudit = AuditLog::query()
            ->where('action_type', AuditActionType::PERMISSION_UPDATED->value)
            ->where('target_entity_id', $staff->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($addedAudit);
        $this->assertTrue(($addedAudit->metadata['changed_fields']['permissions'] ?? false) === true);

        $this->patchJson("/api/v1/users/{$staff->public_id}", [
            'permissions' => [],
        ])->assertOk();

        $removedAudit = AuditLog::query()
            ->where('action_type', AuditActionType::PERMISSION_UPDATED->value)
            ->where('target_entity_id', $staff->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($removedAudit);
        $this->assertTrue(($removedAudit->metadata['changed_fields']['permissions'] ?? false) === true);
        $this->assertSame(
            2,
            AuditLog::query()
                ->where('action_type', AuditActionType::PERMISSION_UPDATED->value)
                ->where('target_entity_id', $staff->id)
                ->count()
        );
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
