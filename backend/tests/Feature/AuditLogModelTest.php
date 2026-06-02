<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class AuditLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_table_has_expected_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('audit_logs'));

        $expectedColumns = [
            'id',
            'actor_user_id',
            'action_type',
            'module',
            'target_entity_type',
            'target_entity_id',
            'metadata',
            'ip_address',
            'user_agent',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(Schema::hasColumn('audit_logs', $column), "Missing column: {$column}");
        }

        $indexes = $this->auditLogIndexNames();

        $this->assertContains('audit_logs_actor_user_id_index', $indexes);
        $this->assertContains('audit_logs_action_type_index', $indexes);
        $this->assertContains('audit_logs_module_index', $indexes);
        $this->assertContains('audit_logs_target_entity_type_index', $indexes);
        $this->assertContains('audit_logs_target_entity_id_index', $indexes);
        $this->assertContains('audit_logs_created_at_index', $indexes);
    }

    public function test_audit_log_can_be_stored_with_safe_metadata_and_actor_relation(): void
    {
        $actor = User::factory()->create();

        $log = AuditLog::factory()->create([
            'actor_user_id' => $actor->id,
            'action_type' => 'updated',
            'module' => 'billing',
            'target_entity_type' => 'invoice',
            'target_entity_id' => 42,
            'metadata' => [
                'before_status' => 'pending',
                'after_status' => 'paid',
                'reason' => 'manual reconciliation',
            ],
            'ip_address' => '203.0.113.11',
            'user_agent' => 'TutorvioTestAgent/1.0',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'actor_user_id' => $actor->id,
            'action_type' => 'updated',
            'module' => 'billing',
            'target_entity_type' => 'invoice',
            'target_entity_id' => 42,
            'ip_address' => '203.0.113.11',
        ]);
        $this->assertTrue($log->actor->is($actor));
        $this->assertSame('paid', $log->metadata['after_status']);
    }

    public function test_audit_log_rejects_sensitive_metadata_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');

        AuditLog::factory()->create([
            'metadata' => [
                'status' => 'updated',
                'access_token' => 'should-not-be-stored',
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function auditLogIndexNames(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('audit_logs')"))
                ->pluck('name')
                ->all();
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return collect(DB::table('information_schema.statistics')
                ->select('index_name')
                ->where('table_schema', DB::raw('DATABASE()'))
                ->where('table_name', 'audit_logs')
                ->distinct()
                ->get())
                ->pluck('index_name')
                ->all();
        }

        $this->fail(sprintf('Unsupported database driver for index assertions: %s', $driver));
    }
}
