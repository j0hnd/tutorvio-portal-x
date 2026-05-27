<?php

namespace App\Http\Resources\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'actor_user' => $this->actorSummary(),
            'action_type' => $this->resource->action_type,
            'module' => $this->resource->module,
            'target_entity_type' => $this->resource->target_entity_type,
            'target_entity_id' => $this->resource->target_entity_id,
            'timestamp' => $this->resource->created_at?->toISOString(),
            'metadata' => $this->sanitizeMetadataForResponse($this->resource->metadata),
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actorSummary(): ?array
    {
        /** @var User|null $actor */
        $actor = $this->resource->actor;

        if (! $actor) {
            return null;
        }

        return [
            'id' => $actor->id,
            'name' => $actor->name,
            'email' => $actor->email,
            'roles' => $actor->relationLoaded('roles')
                ? $actor->roles->pluck('name')->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeMetadataForResponse(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || $this->containsSensitiveKeyPart($key)) {
                continue;
            }

            $sanitized[$key] = $this->sanitizeMetadataValue($value);
        }

        return $sanitized;
    }

    private function sanitizeMetadataValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeMetadataForResponse($value);
        }

        if (is_string($value) && $this->isSensitiveStringValue($value)) {
            return self::REDACTED_VALUE;
        }

        return $value;
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
}
