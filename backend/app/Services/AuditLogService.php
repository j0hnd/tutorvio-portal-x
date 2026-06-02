<?php

namespace App\Services;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Support\LogSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array{ip_address?: string|null, user_agent?: string|null}|null  $requestContext
     */
    public function record(
        ?int $actorUserId,
        AuditActionType|string $actionType,
        AuditModule|string $module,
        string $targetEntityType,
        int|string|null $targetEntityId = null,
        ?array $metadata = null,
        ?array $requestContext = null,
        bool $throwOnFailure = false
    ): ?AuditLog {
        try {
            $context = $this->resolveRequestContext($requestContext);

            return AuditLog::query()->create([
                'actor_user_id' => $actorUserId,
                'action_type' => $this->normalizeActionType($actionType),
                'module' => $this->normalizeModule($module),
                'target_entity_type' => $targetEntityType,
                'target_entity_id' => is_numeric($targetEntityId) ? (int) $targetEntityId : null,
                'metadata' => $this->sanitizeMetadata($metadata),
                'ip_address' => $context['ip_address'],
                'user_agent' => $context['user_agent'],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Audit log write failed.', [
                'actor_user_id' => $actorUserId,
                'action_type' => $this->normalizeActionType($actionType),
                'module' => $this->normalizeModule($module),
                'target_entity_type' => $targetEntityType,
                'target_entity_id' => $targetEntityId,
                'error_type' => $exception::class,
                'error_message' => LogSanitizer::sanitizeString($exception->getMessage()),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function supportedActionTypes(): array
    {
        return AuditActionType::values();
    }

    /**
     * @return array<int, string>
     */
    public function supportedModules(): array
    {
        return AuditModule::values();
    }

    /**
     * @param  array<string, mixed>|null  $requestContext
     * @return array{ip_address: string|null, user_agent: string|null}
     */
    private function resolveRequestContext(?array $requestContext): array
    {
        $request = request();
        $request = $request instanceof Request ? $request : null;

        return [
            'ip_address' => $requestContext['ip_address'] ?? $request?->ip(),
            'user_agent' => $requestContext['user_agent'] ?? $request?->userAgent(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $sanitized = $this->sanitizeMetadataArray($metadata);

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * @param  array<mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadataArray(array $metadata): array
    {
        return LogSanitizer::sanitizeArray($metadata, dropSensitiveKeys: true);
    }

    private function normalizeActionType(AuditActionType|string $actionType): string
    {
        return $actionType instanceof AuditActionType ? $actionType->value : $actionType;
    }

    private function normalizeModule(AuditModule|string $module): string
    {
        return $module instanceof AuditModule ? $module->value : $module;
    }
}
