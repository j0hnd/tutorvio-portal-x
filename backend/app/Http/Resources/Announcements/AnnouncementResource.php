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
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
