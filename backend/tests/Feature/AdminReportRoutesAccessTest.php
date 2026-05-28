<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminReportRoutesAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_report_routes_require_authentication_staff_report_permission_and_admin_or_staff_role(): void
    {
        foreach ($this->adminReportEndpoints() as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        $staff = $this->createUserWithRole('staff');

        Sanctum::actingAs($staff);
        foreach ($this->adminReportEndpoints() as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }

        $staff->givePermissionTo('school_reports.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->adminReportEndpoints() as $endpoint) {
            $this->assertSame(200, $this->getJson($endpoint)->status(), $endpoint);
        }

        Sanctum::actingAs($this->createUserWithRole('teacher'));
        foreach ($this->adminReportEndpoints() as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }

        Sanctum::actingAs($this->createUserWithRole('student'));
        foreach ($this->adminReportEndpoints() as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function adminReportEndpoints(): array
    {
        return [
            '/api/v1/admin/reports/teacher-load',
            '/api/v1/admin/reports/school',
            '/api/v1/admin/reports/active-students',
            '/api/v1/admin/reports/attendance',
            '/api/v1/admin/reports/lesson-completions',
            '/api/v1/admin/reports/teacher-note-completions',
            '/api/v1/admin/reports/student-progress',
            '/api/v1/admin/reports/missed-classes',
            '/api/v1/admin/reports/package-usage',
            '/api/v1/admin/reports/retention-continuation',
            '/api/v1/admin/reports/trial-enrollments',
        ];
    }
}
