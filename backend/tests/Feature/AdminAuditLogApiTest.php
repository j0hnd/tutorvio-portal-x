<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_query_audit_logs_with_filters_and_newest_sorting(): void
    {
        Carbon::setTestNow('2026-05-28 12:00:00');

        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $actorOne = User::factory()->create(['name' => 'Actor One', 'email' => 'actor-one@example.com']);
        $actorOne->assignRole('staff');
        $actorTwo = User::factory()->create(['name' => 'Actor Two', 'email' => 'actor-two@example.com']);
        $actorTwo->assignRole('teacher');

        $oldest = AuditLog::factory()->create([
            'actor_user_id' => $actorOne->id,
            'action_type' => 'users.updated',
            'module' => 'users',
            'target_entity_type' => 'user',
            'target_entity_id' => 7,
            'metadata' => ['note' => 'legacy'],
            'created_at' => now()->subDays(2),
        ]);
        $middle = AuditLog::factory()->create([
            'actor_user_id' => $actorTwo->id,
            'action_type' => 'payment.updated',
            'module' => 'billing',
            'target_entity_type' => 'invoice',
            'target_entity_id' => 12,
            'metadata' => ['note' => 'manual review'],
            'ip_address' => '203.0.113.22',
            'user_agent' => 'TutorvioBrowser/1.0',
            'created_at' => now()->subDay(),
        ]);
        $newest = AuditLog::factory()->create([
            'actor_user_id' => $actorOne->id,
            'action_type' => 'schedule.updated',
            'module' => 'scheduling',
            'target_entity_type' => 'class_schedule',
            'target_entity_id' => 44,
            'metadata' => ['note' => 'critical update'],
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/audit-logs?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('per_page', 2);

        $this->getJson('/api/v1/admin/audit-logs?actor_user_id='.$actorOne->id.'&module=scheduling&target_entity_type=class_schedule&date_from=2026-05-27&date_to=2026-05-28&search=critical')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.0.actor_user.id', $actorOne->id)
            ->assertJsonPath('data.0.actor_user.name', 'Actor One')
            ->assertJsonPath('data.0.action_type', 'schedule.updated')
            ->assertJsonPath('data.0.module', 'scheduling')
            ->assertJsonPath('data.0.target_entity_type', 'class_schedule')
            ->assertJsonPath('data.0.target_entity_id', 44)
            ->assertJsonPath('data.0.metadata.note', 'critical update')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'actor_user',
                    'action_type',
                    'module',
                    'target_entity_type',
                    'target_entity_id',
                    'timestamp',
                    'metadata',
                    'ip_address',
                    'user_agent',
                ]],
            ]);

        $this->assertNotSame($oldest->id, $newest->id);
    }

    public function test_access_rules_for_audit_log_queries_are_enforced(): void
    {
        AuditLog::factory()->create();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();

        $staff->givePermissionTo('audit_logs.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/audit-logs')->assertOk();

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        Sanctum::actingAs($student);
        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    }

    public function test_audit_log_filter_validation_rejects_invalid_ranges_and_formats(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/audit-logs?date_from=2026-05-28&date_to=2026-05-27')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);

        $this->getJson('/api/v1/admin/audit-logs?action_type=bad action')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action_type']);
    }
}
