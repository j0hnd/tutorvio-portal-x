<?php

namespace App\Services;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    private const REDACTED_VALUE = '[REDACTED]';

    private const SENSITIVE_METADATA_KEY_PARTS = [
        'password',
        'passcode',
        'token',
        'secret',
        'private_key',
        'api_key',
        'card_number',
        'credit_card',
        'cvv',
        'cvc',
    ];

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
                'error_message' => $exception->getMessage(),
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
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! $this->isValidMetadataKey($key) || $this->containsSensitiveKeyPart($key)) {
                continue;
            }

            $normalizedKey = trim($key);
            $sanitized[$normalizedKey] = $this->sanitizeMetadataValue($value);
        }

        return $sanitized;
    }

    private function sanitizeMetadataValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeMetadataArray($value);
        }

        if (is_string($value) && $this->isSensitiveStringValue($value)) {
            return self::REDACTED_VALUE;
        }

        return $value;
    }

    private function isValidMetadataKey(string $key): bool
    {
        $trimmed = trim($key);

        if ($trimmed === '') {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._:-]+$/', $trimmed) === 1;
    }

    private function containsSensitiveKeyPart(string $key): bool
    {
        $normalizedKey = strtolower($key);

        foreach (self::SENSITIVE_METADATA_KEY_PARTS as $sensitivePart) {
            if (str_contains($normalizedKey, $sensitivePart)) {
                return true;
            }
        }

        return false;
    }

    private function isSensitiveStringValue(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^Bearer\s+[A-Za-z0-9\-._~+\/]+=*$/i', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/', $trimmed) === 1) {
            return true;
        }

        return preg_match('/\b(?:\d[ -]*?){13,19}\b/', $trimmed) === 1;
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
