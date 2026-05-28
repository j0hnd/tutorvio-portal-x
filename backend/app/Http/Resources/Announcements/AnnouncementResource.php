<?php

namespace App\Http\Resources\Announcements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $readState = $this->resource->relationLoaded('readStates')
            ? $this->resource->readStates->first()
            : null;

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'content' => $this->resource->body,
            'body' => $this->resource->body,
            'status' => $this->resource->status,
            'scheduled_at' => $this->resource->scheduled_at,
            'published_at' => $this->resource->published_at,
            'archived_at' => $this->resource->archived_at,
            'created_by' => $this->resource->author_id,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->resource->author?->id,
                'name' => $this->resource->author?->name,
                'email' => $this->resource->author?->email,
            ]),
            'archived_by' => $this->resource->archived_by,
            'recipient_count' => $this->whenCounted('recipients'),
            'is_read' => $this->when($this->resource->relationLoaded('readStates'), fn () => $readState?->read_at !== null),
            'read_status' => $this->when($this->resource->relationLoaded('readStates'), fn () => $readState?->read_at === null ? 'unread' : 'read'),
            'read_at' => $this->when($this->resource->relationLoaded('readStates'), fn () => $readState?->read_at),
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
}
