<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        AuditLog::factory()->create();

        $this->actingAsUserWithRole('admin');

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk();
    }

    public function test_staff_with_audit_logs_permission_can_query_audit_logs(): void
    {
        AuditLog::factory()->create();

        $this->actingAsUserWithRole('staff', ['audit_logs.view']);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk();
    }

    public function test_staff_without_audit_logs_permission_cannot_query_audit_logs(): void
    {
        AuditLog::factory()->create();

        $this->actingAsUserWithRole('staff');

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    }

    public function test_teacher_cannot_query_audit_logs(): void
    {
        AuditLog::factory()->create();

        $this->actingAsUserWithRole('teacher');

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    }

    public function test_student_cannot_query_audit_logs(): void
    {
        AuditLog::factory()->create();

        $this->actingAsUserWithRole('student');

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    }

    public function test_filter_by_actor_user_id_works_for_admin_audit_log_queries(): void
    {
        $this->actingAsUserWithRole('admin');

        $actorOne = User::factory()->create();
        $actorOne->assignRole('staff');
        $actorTwo = User::factory()->create();
        $actorTwo->assignRole('staff');

        $match = AuditLog::factory()->create(['actor_user_id' => $actorOne->id]);
        AuditLog::factory()->create(['actor_user_id' => $actorTwo->id]);

        $this->getJson('/api/v1/admin/audit-logs?actor_user_id='.$actorOne->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->public_id)
            ->assertJsonPath('data.0.actor_user.id', $actorOne->public_id);
    }

    public function test_filter_by_action_type_works_for_admin_audit_log_queries(): void
    {
        $this->actingAsUserWithRole('admin');

        $match = AuditLog::factory()->create(['action_type' => 'schedule.updated']);
        AuditLog::factory()->create(['action_type' => 'users.updated']);

        $this->getJson('/api/v1/admin/audit-logs?action_type=schedule.updated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->public_id)
            ->assertJsonPath('data.0.action_type', 'schedule.updated');
    }

    public function test_filter_by_module_works_for_admin_audit_log_queries(): void
    {
        $this->actingAsUserWithRole('admin');

        $match = AuditLog::factory()->create(['module' => 'scheduling']);
        AuditLog::factory()->create(['module' => 'users']);

        $this->getJson('/api/v1/admin/audit-logs?module=scheduling')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->public_id)
            ->assertJsonPath('data.0.module', 'scheduling');
    }

    public function test_filter_by_target_entity_works_for_admin_audit_log_queries(): void
    {
        $this->actingAsUserWithRole('admin');

        $match = AuditLog::factory()->create([
            'target_entity_type' => 'class_schedule',
            'target_entity_id' => 44,
        ]);
        AuditLog::factory()->create([
            'target_entity_type' => 'invoice',
            'target_entity_id' => 44,
        ]);
        AuditLog::factory()->create([
            'target_entity_type' => 'class_schedule',
            'target_entity_id' => 99,
        ]);

        $this->getJson('/api/v1/admin/audit-logs?target_entity_type=class_schedule&target_entity_id=44')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->public_id)
            ->assertJsonPath('data.0.target_entity_type', 'class_schedule')
            ->assertJsonPath('data.0.target_entity_id', 44);
    }

    public function test_filter_by_date_range_works_for_admin_audit_log_queries(): void
    {
        Carbon::setTestNow('2026-05-28 12:00:00');
        $this->actingAsUserWithRole('admin');

        AuditLog::factory()->create(['created_at' => now()->subDays(3)]);
        $inRangeOne = AuditLog::factory()->create(['created_at' => now()->subDay()]);
        $inRangeTwo = AuditLog::factory()->create(['created_at' => now()]);

        $this->getJson('/api/v1/admin/audit-logs?date_from=2026-05-27&date_to=2026-05-28')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $inRangeTwo->public_id)
            ->assertJsonPath('data.1.id', $inRangeOne->public_id);
    }

    public function test_pagination_works_for_admin_audit_log_queries(): void
    {
        Carbon::setTestNow('2026-05-28 12:00:00');
        $this->actingAsUserWithRole('admin');

        $oldest = AuditLog::factory()->create(['created_at' => now()->subDays(2)]);
        $middle = AuditLog::factory()->create(['created_at' => now()->subDay()]);
        $newest = AuditLog::factory()->create(['created_at' => now()]);

        $this->getJson('/api/v1/admin/audit-logs?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.id', $newest->public_id)
            ->assertJsonPath('data.1.id', $middle->public_id);

        $this->getJson('/api/v1/admin/audit-logs?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $oldest->public_id);
    }

    public function test_results_are_sorted_newest_first(): void
    {
        Carbon::setTestNow('2026-05-28 12:00:00');
        $this->actingAsUserWithRole('admin');

        $oldest = AuditLog::factory()->create(['created_at' => now()->subDays(2)]);
        $middle = AuditLog::factory()->create(['created_at' => now()->subDay()]);
        $newest = AuditLog::factory()->create(['created_at' => now()]);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->public_id)
            ->assertJsonPath('data.1.id', $middle->public_id)
            ->assertJsonPath('data.2.id', $oldest->public_id);
    }

    public function test_search_matches_audit_text_and_actor_identity(): void
    {
        $this->actingAsUserWithRole('admin');

        $actor = User::factory()->create([
            'name' => 'Operations Searcher',
            'email' => 'operations-searcher@example.com',
        ]);
        $actor->assignRole('staff');

        $textMatch = AuditLog::factory()->create([
            'action_type' => 'lesson.material_uploaded',
            'module' => 'learning_resources',
            'target_entity_type' => 'learning_resource',
        ]);
        AuditLog::factory()->create([
            'actor_user_id' => $actor->id,
            'action_type' => 'users.updated',
            'module' => 'users',
        ]);
        AuditLog::factory()->create([
            'action_type' => 'billing.invoice_created',
            'module' => 'billing',
            'metadata' => ['context' => 'plain'],
        ]);
        $metadataMatch = AuditLog::factory()->create([
            'action_type' => 'users.updated',
            'module' => 'users',
            'metadata' => ['context' => 'metadata-search-token'],
        ]);

        $this->getJson('/api/v1/admin/audit-logs?search=material')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $textMatch->public_id);

        $this->getJson('/api/v1/admin/audit-logs?search=operations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor_user.id', $actor->public_id);

        $this->getJson('/api/v1/admin/audit-logs?search=metadata-search-token')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $metadataMatch->public_id);
    }

    public function test_sensitive_metadata_fields_are_not_returned_in_audit_log_api_responses(): void
    {
        Carbon::setTestNow('2026-05-28 12:00:00');
        $admin = $this->actingAsUserWithRole('admin');

        DB::table('audit_logs')->insert([
            'actor_user_id' => $admin->id,
            'action_type' => 'users.updated',
            'module' => 'users',
            'target_entity_type' => 'user',
            'target_entity_id' => 10,
            'metadata' => json_encode([
                'safe_note' => 'visible',
                'access_token' => 'should-not-be-returned',
                'nested' => [
                    'api_key' => 'remove-this',
                    'description' => 'keep-this',
                ],
            ], JSON_THROW_ON_ERROR),
            'ip_address' => '203.0.113.40',
            'user_agent' => 'TutorvioTestAgent/1.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.metadata.safe_note', 'visible')
            ->assertJsonPath('data.0.metadata.nested.description', 'keep-this')
            ->assertJsonMissingPath('data.0.metadata.access_token')
            ->assertJsonMissingPath('data.0.metadata.nested.api_key');
    }

    public function test_audit_log_filter_validation_rejects_invalid_ranges_and_formats(): void
    {
        $this->actingAsUserWithRole('admin');

        $this->getJson('/api/v1/admin/audit-logs?date_from=2026-05-28&date_to=2026-05-27')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);

        $this->getJson('/api/v1/admin/audit-logs?action_type=bad action')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action_type']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function actingAsUserWithRole(string $role, array $permissions = []): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Sanctum::actingAs($user);

        return $user;
    }
}
