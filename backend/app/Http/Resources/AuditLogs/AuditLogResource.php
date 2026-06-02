<?php

namespace App\Http\Resources\AuditLogs;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\LogSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
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
            'id' => $this->publicId($actor),
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

        return LogSanitizer::sanitizeArray($metadata, dropSensitiveKeys: true);
    }
}
