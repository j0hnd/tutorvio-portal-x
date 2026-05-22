<?php

namespace App\Http\Resources\LearningResources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'resource_type' => $this->resource->resource_type,
            'url' => $this->resource->isExternalLink() ? $this->resource->url : null,
            'original_filename' => $this->resource->original_filename,
            'mime_type' => $this->resource->mime_type,
            'file_size' => $this->resource->file_size,
            'preview_metadata' => $this->resource->preview_metadata,
            'course' => $this->resource->course,
            'level' => $this->resource->level,
            'visibility' => $this->resource->visibility,
            'has_file' => $this->resource->hasStoredFile(),
            'created_by' => $this->resource->created_by,
            'created_by_user' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->resource->createdBy?->id,
                'name' => $this->resource->createdBy?->name,
                'email' => $this->resource->createdBy?->email,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
