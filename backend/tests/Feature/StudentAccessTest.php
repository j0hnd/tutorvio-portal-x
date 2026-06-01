<?php

namespace Tests\Feature;

use App\Models\StudentProgressRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_cannot_access_another_students_progress_summary(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');

        $student->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);
        $otherStudent->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);

        StudentProgressRecord::factory()->create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $teacher->id,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$otherStudent->public_id}/progress-summary")
            ->assertForbidden();
    }

    public function test_student_cannot_access_admin_audit_logs(): void
    {
        $student = $this->userWithRole('student');

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
