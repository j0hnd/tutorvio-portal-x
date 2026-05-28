<?php

namespace Tests\Feature;

use App\Models\IssueReport;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

        $this->getJson("/api/v1/students/{$student->id}/package-summary")
            ->assertForbidden();

        $staff->givePermissionTo('subscriptions.view');

        $this->getJson("/api/v1/students/{$student->id}/package-summary")
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

        $this->getJson("/api/v1/issue-reports/{$issueReport->id}")
            ->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
