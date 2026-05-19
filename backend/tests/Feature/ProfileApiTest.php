<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
                 ->assertJsonPath('data.id', $student->id)
                 ->assertJsonPath('data.student_profile.preferences', 'Morning classes');
    }

    public function test_student_cannot_view_another_student_profile()
    {
        $student1 = User::factory()->create();
        $student1->assignRole('student');
        
        $student2 = User::factory()->create();
        $student2->assignRole('student');

        $response = $this->actingAs($student1)->getJson('/api/v1/users/' . $student2->id . '/profile');

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
                 ->assertJsonPath('data.id', $teacher->id)
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

        $response = $this->actingAs($teacher)->getJson('/api/v1/users/' . $student->id . '/profile');

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $student->id);
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

        $response = $this->actingAs($teacher)->getJson('/api/v1/users/' . $student->id . '/profile');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_all_profiles()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $student = User::factory()->create();
        $student->assignRole('student');

        $response = $this->actingAs($admin)->getJson('/api/v1/users/' . $student->id . '/profile');

        $response->assertStatus(200);
    }

    public function test_staff_access_follows_permissions()
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $student = User::factory()->create();
        $student->assignRole('student');

        // Without permission
        $response = $this->actingAs($staff)->getJson('/api/v1/users/' . $student->id . '/profile');
        $response->assertStatus(403);

        // With permission
        $staff->givePermissionTo('users.view');
        
        $response2 = $this->actingAs($staff)->getJson('/api/v1/users/' . $student->id . '/profile');
        $response2->assertStatus(200);
    }

    public function test_restricted_fields_cannot_be_updated_by_unauthorized_users()
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
            ]
        ]);

        $response->assertStatus(200);
        
        // Ensure preferences changed but internal notes didn't
        $student->refresh();
        $this->assertEquals('New prefs', $student->studentProfile->preferences);
        $this->assertNull($student->studentProfile->internal_notes);
    }
}
