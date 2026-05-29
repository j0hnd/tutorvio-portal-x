<?php

namespace App\Http\Resources\LearningResources;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class LearningResourceResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->resource->pivot;
        $canViewAdminFields = $this->canViewAdminFields($request, 'learning_resources.view');

        $data = [
            'id' => $this->publicId($this->resource),
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'resource_type' => $this->resource->resource_type,
            'url' => $this->resource->isExternalLink() ? $this->resource->url : null,
            'download' => $this->resource->isExternalLink() || $this->resource->hasStoredFile() ? [
                'endpoint' => url('/api/v1/learning-resources/'.$this->publicId($this->resource).'/download'),
                'type' => $this->resource->isExternalLink() ? 'link' : 'file',
            ] : null,
            'original_filename' => $this->resource->original_filename,
            'mime_type' => $this->resource->mime_type,
            'file_size' => $this->resource->file_size,
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
            'has_file' => $this->resource->hasStoredFile(),
            'version' => [
                'number' => $this->resource->currentVersionNumber(),
                'history_endpoint' => $canViewAdminFields
                    ? url('/api/v1/learning-resources/'.$this->publicId($this->resource).'/versions')
                    : null,
            ],
        ];

        if ($canViewAdminFields) {
            $data += [
                'preview_metadata' => $this->resource->preview_metadata,
                'visibility' => $this->resource->visibility,
                'created_by' => $this->resource->created_by,
                'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->resource->createdBy, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        if ($canViewAdminFields) {
            $data += [
                'assignment' => $this->when($pivot !== null && $pivot->getAttribute('assigned_at') !== null, fn () => [
                    'assigned_by' => $pivot->getAttribute('assigned_by'),
                    'assigned_at' => $pivot->getAttribute('assigned_at') === null
                        ? null
                        : Carbon::parse($pivot->getAttribute('assigned_at')),
                ]),
                'course_attachment' => $this->when($pivot !== null && $pivot->getAttribute('attached_at') !== null, fn () => [
                    'attached_by' => $pivot->getAttribute('attached_by'),
                    'attached_at' => $pivot->getAttribute('attached_at') === null
                        ? null
                        : Carbon::parse($pivot->getAttribute('attached_at')),
                ]),
            ];
        }

        return $data;
    }
}
