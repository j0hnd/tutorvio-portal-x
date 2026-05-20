<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Material;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_student_dashboard_only_returns_own_summary(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        Lesson::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => 'scheduled',
        ]);

        $material = Material::create(['title' => 'Placement Prep']);
        $material->students()->attach($student->id, [
            'assigned_at' => now(),
            'completed_at' => now(),
        ]);

        Subscription::create([
            'user_id' => $student->id,
            'plan_name' => 'Starter',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.summary.classes.total', 1)
            ->assertJsonPath('data.summary.materials.assigned', 1)
            ->assertJsonPath('data.summary.materials.completed', 1)
            ->assertJsonPath('data.summary.subscription.plan_name', 'Starter')
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.students');
    }

    public function test_teacher_dashboard_only_returns_teacher_scoped_summary(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $otherStudent->studentProfile()->create(['assigned_teacher_id' => $otherTeacher->id]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => 'completed',
        ]);

        Lesson::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $otherTeacher->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => 'completed',
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.summary.students.assigned', 1)
            ->assertJsonPath('data.summary.classes.total', 1)
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.materials');
    }

    public function test_staff_dashboard_only_includes_permitted_sections(): void
    {
        Role::findByName('staff')->syncPermissions(['students.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.summary.students.total', 1)
            ->assertJsonPath('data.sections', ['students'])
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.classes');
    }

    public function test_admin_dashboard_returns_admin_summary(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.summary.users.total', 2)
            ->assertJsonPath('data.summary.students.total', 1)
            ->assertJsonPath('data.sections', ['users', 'students', 'classes']);
    }
}
