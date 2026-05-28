<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_teacher_can_only_view_package_summary_for_assigned_students(): void
    {
        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $assignedStudent = $this->userWithRole('student');
        $unassignedStudent = $this->userWithRole('student');

        $assignedStudent->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);
        $unassignedStudent->studentProfile()->create(['assigned_teacher_id' => $otherTeacher->id]);

        Subscription::factory()->create([
            'user_id' => $assignedStudent->id,
            'internal_notes' => 'Private billing note',
        ]);
        Subscription::factory()->create([
            'user_id' => $unassignedStudent->id,
            'internal_notes' => 'Unassigned private note',
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/students/{$assignedStudent->id}/package-summary")
            ->assertOk()
            ->assertJsonMissingPath('data.internal_notes');

        $this->getJson("/api/v1/students/{$unassignedStudent->id}/package-summary")
            ->assertForbidden()
            ->assertJsonMissing(['internal_notes' => 'Unassigned private note']);
    }

    public function test_teacher_profile_lookup_for_unassigned_student_returns_not_found(): void
    {
        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student');
        $student->studentProfile()->create(['assigned_teacher_id' => $otherTeacher->id]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/users/{$student->id}/profile")
            ->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
