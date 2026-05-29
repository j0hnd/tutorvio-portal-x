<?php

namespace App\Http\Resources\Announcements;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $readState = $this->resource->relationLoaded('readStates')
            ? $this->resource->readStates->first()
            : null;

        $canViewAdminFields = $this->canViewAdminFields($request, 'announcements.manage');

        $data = [
            'id' => $this->publicId($this->resource),
            'title' => $this->resource->title,
            'content' => $this->resource->body,
            'body' => $this->resource->body,
            'status' => $this->resource->status,
            'scheduled_at' => $this->resource->scheduled_at,
            'published_at' => $this->resource->published_at,
            'author' => $this->whenLoaded('author', fn () => $this->userSummary($this->resource->author, $request)),
            'is_read' => $this->when($this->resource->relationLoaded('readStates'), fn () => $readState?->read_at !== null),
            'read_status' => $this->when($this->resource->relationLoaded('readStates'), fn () => $readState?->read_at === null ? 'unread' : 'read'),
            'read_at' => $this->when($this->resource->relationLoaded('readStates'), fn () => $readState?->read_at),
        ];

        if ($canViewAdminFields) {
            $data += [
                'archived_at' => $this->resource->archived_at,
                'created_by' => $this->resource->author_id,
                'archived_by' => $this->resource->archived_by,
                'recipient_count' => $this->whenCounted('recipients'),
                'targets' => $this->whenLoaded('targets', fn () => $this->resource->targets->map(fn ($target) => [
                    'id' => $target->id,
                    'type' => $target->target_type,
                    'target_id' => $target->target_id,
                    'user_id' => $target->user_id,
                    'role' => $target->role,
                    'metadata' => $target->metadata,
                ])->values()),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
