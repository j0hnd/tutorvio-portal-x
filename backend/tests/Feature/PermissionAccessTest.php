<?php

namespace Tests\Feature;

use App\Models\IssueReport;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_staff_package_summary_access_requires_subscriptions_permission(): void
    {
        $staff = $this->userWithRole('staff');
        $student = $this->userWithRole('student');
        Subscription::factory()->create(['user_id' => $student->id]);

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
            ->assertForbidden();

        $staff->givePermissionTo('subscriptions.view');

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
            ->assertOk();
    }

    public function test_seeded_staff_role_starts_without_baseline_permissions(): void
    {
        $staffRole = Role::findByName('staff');

        $this->assertCount(0, $staffRole->permissions);
        $this->assertFalse($staffRole->hasPermissionTo('admin.access'));
        $this->assertFalse($staffRole->hasPermissionTo('users.view'));
        $this->assertFalse($staffRole->hasPermissionTo('school_reports.view'));
    }

    public function test_admin_only_endpoints_reject_non_admin_users_even_with_permissions(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo('admin.access');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/access')
            ->assertForbidden();
    }

    public function test_permission_gated_staff_routes_require_correct_permissions(): void
    {
        $staff = $this->userWithRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/reports/school')
            ->assertForbidden();

        $staff->givePermissionTo('school_reports.view');

        $this->getJson('/api/v1/admin/reports/school')
            ->assertOk();
    }

    public function test_issue_report_show_returns_not_found_for_non_reporter(): void
    {
        $reporter = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');

        $issueReport = IssueReport::query()->create([
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => IssueReport::PRIORITY_NORMAL,
            'reporter_id' => $reporter->id,
            'title' => 'Camera issue',
            'description' => 'Unable to access camera in class.',
        ]);

        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/v1/issue-reports/{$issueReport->public_id}")
            ->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
