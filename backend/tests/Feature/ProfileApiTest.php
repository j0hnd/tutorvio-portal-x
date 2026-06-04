<?php

namespace Tests\Feature;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        // Ensure permissions exist
        Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.update', 'guard_name' => 'web']);
    }

    public function test_student_can_view_own_profile()
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'preferences' => 'Morning classes',
        ]);

        $response = $this->actingAs($student)->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $student->public_id)
            ->assertJsonPath('data.student_profile.preferences', 'Morning classes');
        $this->assertIsString($response->json('data.id'));
        $this->assertTrue(Str::isUlid($response->json('data.id')));
    }

    public function test_student_cannot_view_another_student_profile()
    {
        $student1 = User::factory()->create();
        $student1->assignRole('student');

        $student2 = User::factory()->create();
        $student2->assignRole('student');

        $response = $this->actingAs($student1)->getJson('/api/v1/users/'.$student2->public_id.'/profile');

        $response->assertStatus(403);
    }

    public function test_student_cannot_see_internal_remarks()
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'internal_notes' => 'Secret note',
        ]);

        $response = $this->actingAs($student)->getJson('/api/v1/profile');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('internal_notes', $response->json('data.student_profile'));
    }

    public function test_teacher_can_view_own_profile()
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        TeacherProfile::create([
            'user_id' => $teacher->id,
            'bio' => 'Great teacher',
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $teacher->public_id)
            ->assertJsonPath('data.teacher_profile.bio', 'Great teacher');
    }

    public function test_teacher_can_view_assigned_student_profile()
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/v1/users/'.$student->public_id.'/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $student->public_id);
    }

    public function test_teacher_cannot_view_unassigned_student_profile()
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $teacher2 = User::factory()->create();

        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher2->id, // Assigned to another
        ]);

        $response = $this->actingAs($teacher)->getJson('/api/v1/users/'.$student->public_id.'/profile');

        $response->assertStatus(404);
    }

    public function test_admin_can_view_all_profiles()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $student = User::factory()->create();
        $student->assignRole('student');

        $response = $this->actingAs($admin)->getJson('/api/v1/users/'.$student->public_id.'/profile');

        $response->assertStatus(200);
    }

    public function test_staff_access_follows_permissions()
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $student = User::factory()->create();
        $student->assignRole('student');

        // Without permission
        $response = $this->actingAs($staff)->getJson('/api/v1/users/'.$student->public_id.'/profile');
        $response->assertStatus(403);

        // With permission
        $staff->givePermissionTo('users.view');

        $response2 = $this->actingAs($staff)->getJson('/api/v1/users/'.$student->public_id.'/profile');
        $response2->assertStatus(200);
    }

    public function test_assigned_teacher_can_update_only_teacher_notes_on_student_profile(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'english_level' => 'A1',
            'preferences' => 'Morning classes',
            'teacher_notes' => 'Old teacher note',
        ]);

        $response = $this->actingAs($teacher)->patchJson('/api/v1/users/'.$student->public_id.'/profile', [
            'student_profile' => [
                'teacher_notes' => 'Needs speaking practice',
            ],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.student_profile.teacher_notes', 'Needs speaking practice');

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'english_level' => 'A1',
            'preferences' => 'Morning classes',
            'teacher_notes' => 'Needs speaking practice',
        ]);
    }

    public function test_unassigned_teacher_cannot_update_student_profile(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $assignedTeacher = User::factory()->create();
        $assignedTeacher->assignRole('teacher');

        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'assigned_teacher_id' => $assignedTeacher->id,
            'teacher_notes' => 'Original note',
        ]);

        $response = $this->actingAs($teacher)->patchJson('/api/v1/users/'.$student->public_id.'/profile', [
            'student_profile' => [
                'teacher_notes' => 'Unauthorized note',
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'assigned_teacher_id' => $assignedTeacher->id,
            'teacher_notes' => 'Original note',
        ]);
    }

    public function test_assigned_teacher_cannot_update_student_owned_or_admin_student_profile_fields(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $replacementTeacher = User::factory()->create();
        $replacementTeacher->assignRole('teacher');

        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'english_level' => 'A1',
            'notes' => 'Admin note',
            'internal_notes' => 'Internal note',
            'preferences' => 'Morning classes',
            'goals' => 'Conversation',
            'learning_concerns' => 'Grammar',
        ]);

        $response = $this->actingAs($teacher)->patchJson('/api/v1/users/'.$student->public_id.'/profile', [
            'student_profile' => [
                'assigned_teacher_id' => $replacementTeacher->id,
                'english_level' => 'C2',
                'notes' => 'Changed admin note',
                'internal_notes' => 'Changed internal note',
                'preferences' => 'Evening classes',
                'goals' => 'Business English',
                'learning_concerns' => 'Pronunciation',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'student_profile.assigned_teacher_id',
                'student_profile.english_level',
                'student_profile.notes',
                'student_profile.internal_notes',
                'student_profile.preferences',
                'student_profile.goals',
                'student_profile.learning_concerns',
            ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'english_level' => 'A1',
            'notes' => 'Admin note',
            'internal_notes' => 'Internal note',
            'preferences' => 'Morning classes',
            'goals' => 'Conversation',
            'learning_concerns' => 'Grammar',
        ]);
    }

    public function test_admin_can_fully_update_student_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $student = User::factory()->create([
            'name' => 'Original Student',
            'phone' => '555-1000',
            'timezone' => 'UTC',
        ]);
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'english_level' => 'A1',
            'teacher_notes' => 'Old teacher note',
        ]);

        $response = $this->actingAs($admin)->patchJson('/api/v1/users/'.$student->public_id.'/profile', [
            'name' => 'Updated Student',
            'phone' => '555-2000',
            'timezone' => 'Asia/Manila',
            'student_profile' => [
                'assigned_teacher_id' => $teacher->id,
                'english_level' => 'B2',
                'current_level' => 'Intermediate',
                'course' => 'General English',
                'class_type' => 'One-on-one',
                'start_date' => '2026-06-01',
                'notes' => 'Admin note',
                'teacher_notes' => 'Teacher-visible note',
                'internal_notes' => 'Internal admin note',
            ],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Student')
            ->assertJsonPath('data.student_profile.assigned_teacher_id', $teacher->public_id)
            ->assertJsonPath('data.student_profile.teacher_notes', 'Teacher-visible note')
            ->assertJsonPath('data.student_profile.internal_notes', 'Internal admin note');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Student',
            'phone' => '555-2000',
            'timezone' => 'Asia/Manila',
        ]);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'english_level' => 'B2',
            'current_level' => 'Intermediate',
            'course' => 'General English',
            'class_type' => 'One-on-one',
            'notes' => 'Admin note',
            'teacher_notes' => 'Teacher-visible note',
            'internal_notes' => 'Internal admin note',
        ]);
    }

    public function test_restricted_fields_are_rejected_for_unauthorized_users()
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        StudentProfile::create([
            'user_id' => $student->id,
            'preferences' => 'Old prefs',
        ]);

        // Student updates own preferences -> Allowed
        $response = $this->actingAs($student)->patchJson('/api/v1/profile', [
            'student_profile' => [
                'preferences' => 'New prefs',
                'internal_notes' => 'Hacked notes',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_profile.internal_notes');

        $student->refresh();
        $this->assertEquals('Old prefs', $student->studentProfile->preferences);
        $this->assertNull($student->studentProfile->internal_notes);

        $auditLog = AuditLog::query()
            ->where('action_type', AuditActionType::STUDENT_UPDATED->value)
            ->where('module', AuditModule::STUDENTS->value)
            ->where('target_entity_type', 'student_profile')
            ->latest('id')
            ->first();

        $this->assertNull($auditLog);
    }
}
