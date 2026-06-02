<?php

namespace Tests\Feature;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_audit_log_successfully(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditLogService::class);

        $log = $service->record(
            actorUserId: $actor->id,
            actionType: AuditActionType::LESSON_CREATED,
            module: AuditModule::LESSONS,
            targetEntityType: 'lesson',
            targetEntityId: 99,
            metadata: ['source' => 'api'],
            requestContext: [
                'ip_address' => '203.0.113.14',
                'user_agent' => 'TutorvioTests/1.0',
            ]
        );

        $this->assertNotNull($log);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'actor_user_id' => $actor->id,
            'action_type' => AuditActionType::LESSON_CREATED->value,
            'module' => AuditModule::LESSONS->value,
            'target_entity_type' => 'lesson',
            'target_entity_id' => 99,
            'ip_address' => '203.0.113.14',
            'user_agent' => 'TutorvioTests/1.0',
        ]);
    }

    public function test_it_sanitizes_metadata_before_storing(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditLogService::class);

        $log = $service->record(
            actorUserId: $actor->id,
            actionType: AuditActionType::STUDENT_UPDATED,
            module: AuditModule::STUDENTS,
            targetEntityType: 'student',
            targetEntityId: 7,
            metadata: [
                'status' => 'updated',
                'external_ref' => 'EXT-7',
                'nested' => [
                    'safe_note' => 'ok',
                    'access_token' => 'remove-me',
                ],
            ]
        );

        $this->assertNotNull($log);
        $this->assertSame([
            'status' => 'updated',
            'external_ref' => 'EXT-7',
            'nested' => [
                'safe_note' => 'ok',
            ],
        ], $log->metadata);
    }

    public function test_it_handles_missing_optional_metadata(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditLogService::class);

        $log = $service->record(
            actorUserId: $actor->id,
            actionType: AuditActionType::ATTENDANCE_MARKED,
            module: AuditModule::ATTENDANCE,
            targetEntityType: 'attendance',
            targetEntityId: 101,
        );

        $this->assertNotNull($log);
        $this->assertNull($log->metadata);
    }

    public function test_it_excludes_invalid_and_sensitive_metadata_keys(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditLogService::class);

        $log = $service->record(
            actorUserId: $actor->id,
            actionType: AuditActionType::PAYMENT_UPDATED,
            module: AuditModule::BILLING,
            targetEntityType: 'invoice',
            targetEntityId: 51,
            metadata: [
                '' => 'drop-empty-key',
                'bad key' => 'drop-invalid-format',
                'api_token' => 'drop-sensitive-key',
                'card_note' => 'keep',
                'reference_value' => '4111 1111 1111 1111',
            ]
        );

        $this->assertNotNull($log);
        $this->assertSame([
            'reference_value' => '[REDACTED]',
        ], $log->metadata);
    }

    public function test_it_does_not_save_sensitive_values_in_the_database(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditLogService::class);

        $log = $service->record(
            actorUserId: $actor->id,
            actionType: AuditActionType::PAYMENT_UPDATED,
            module: AuditModule::BILLING,
            targetEntityType: 'invoice',
            targetEntityId: 51,
            metadata: [
                'authorization_header' => 'Bearer sensitive-token-value',
                'gateway_reference' => '4111 1111 1111 1111',
                'safe_note' => 'payment confirmed',
            ]
        );

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('authorization_header', $log->metadata);
        $this->assertSame('[REDACTED]', $log->metadata['gateway_reference'] ?? null);
        $this->assertSame('payment confirmed', $log->metadata['safe_note'] ?? null);

        $rawMetadata = DB::table('audit_logs')
            ->where('id', $log->id)
            ->value('metadata');

        $this->assertIsString($rawMetadata);
        $this->assertStringNotContainsString('sensitive-token-value', $rawMetadata);
        $this->assertStringNotContainsString('4111 1111 1111 1111', $rawMetadata);
    }

    public function test_it_does_not_throw_on_logging_failure_by_default(): void
    {
        $service = app(AuditLogService::class);

        $result = $service->record(
            actorUserId: 999999,
            actionType: AuditActionType::ROLE_UPDATED,
            module: AuditModule::USERS,
            targetEntityType: 'role',
            targetEntityId: 3
        );

        $this->assertNull($result);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_it_can_throw_on_logging_failure_when_requested(): void
    {
        $service = app(AuditLogService::class);

        $this->expectException(QueryException::class);

        $service->record(
            actorUserId: 999999,
            actionType: AuditActionType::PERMISSION_UPDATED,
            module: AuditModule::PERMISSIONS,
            targetEntityType: 'permission',
            targetEntityId: 1,
            throwOnFailure: true
        );
    }
}
