<?php

namespace App\Http\Resources\LearningResources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
            'download' => $this->resource->isExternalLink() || $this->resource->hasStoredFile() ? [
                'endpoint' => url('/api/v1/learning-resources/'.$this->resource->id.'/download'),
                'type' => $this->resource->isExternalLink() ? 'link' : 'file',
            ] : null,
            'original_filename' => $this->resource->original_filename,
            'mime_type' => $this->resource->mime_type,
            'file_size' => $this->resource->file_size,
            'preview_metadata' => $this->resource->preview_metadata,
            'course' => $this->resource->course,
            'level' => $this->resource->level,
            'grouping' => [
                'course' => $this->resource->course === null ? null : [
                    'name' => $this->resource->course,
                ],
                'level' => $this->resource->level === null ? null : [
                    'name' => $this->resource->level,
                ],
                'is_generic' => $this->resource->course === null && $this->resource->level === null,
            ],
            'visibility' => $this->resource->visibility,
            'has_file' => $this->resource->hasStoredFile(),
            'created_by' => $this->resource->created_by,
            'created_by_user' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->resource->createdBy?->id,
                'name' => $this->resource->createdBy?->name,
                'email' => $this->resource->createdBy?->email,
            ]),
            'assignment' => $this->when($this->resource->pivot !== null, fn () => [
                'assigned_by' => $this->resource->pivot->assigned_by,
                'assigned_at' => $this->resource->pivot->assigned_at === null
                    ? null
                    : Carbon::parse($this->resource->pivot->assigned_at),
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
