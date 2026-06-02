<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_only_access_route_rejects_student_and_teacher(): void
    {
        $student = $this->userWithRole('student');
        $teacher = $this->userWithRole('teacher');

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/admin/access')->assertForbidden();

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/admin/access')->assertForbidden();
    }

    public function test_admin_can_access_admin_only_access_route(): void
    {
        $admin = $this->userWithRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/access')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
