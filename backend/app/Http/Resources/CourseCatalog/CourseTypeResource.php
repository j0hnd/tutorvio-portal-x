<?php

namespace App\Http\Resources\CourseCatalog;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseTypeResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a course type into a public-safe catalog response.
     *
     * Public fields expose the course type public ID, display metadata, archive
     * status, and optional program counts. User audit references and timestamps
     * are admin-only or require course_programs.view. Database primary keys should
     * not be exposed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->publicId($this->resource),
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'sort_order' => $this->resource->sort_order,
            'is_archived' => $this->resource->is_archived,
            'archived_at' => $this->resource->archived_at,
            'programs_count' => $this->whenCounted('programs'),
        ];

        if ($this->canViewAdminFields($request, 'course_programs.view')) {
            $data += [
                'archived_by' => $this->resource->archived_by,
                'created_by' => $this->resource->created_by,
                'updated_by' => $this->resource->updated_by,
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
