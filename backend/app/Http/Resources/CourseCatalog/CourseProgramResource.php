<?php

namespace App\Http\Resources\CourseCatalog;

use App\Http\Resources\LearningResources\LearningResourceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CourseProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $studentView = $request->user()?->hasRole('student') === true;

        return [
            'id' => $this->resource->id,
            'course_type_id' => $this->resource->course_type_id,
            'course_type' => $this->whenLoaded('courseType', fn () => new CourseTypeResource($this->resource->courseType)),
            'title' => $this->resource->title,
            'name' => $this->resource->title,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'placement_level' => $this->resource->placement_level,
            'number_of_sessions' => $this->resource->number_of_sessions,
            'lesson_structure' => $this->resource->lesson_structure,
            'milestones' => $this->resource->milestones,
            'learning_resources' => LearningResourceResource::collection($this->whenLoaded('learningResources')),
            'is_archived' => $this->when(! $studentView, $this->resource->is_archived),
            'archived_at' => $this->when(! $studentView, $this->resource->archived_at),
            'archived_by' => $this->when(! $studentView, $this->resource->archived_by),
            'created_by' => $this->when(! $studentView, $this->resource->created_by),
            'updated_by' => $this->when(! $studentView, $this->resource->updated_by),
            'resource_attachment' => $this->when(! $studentView && $this->resource->pivot !== null, fn () => [
                'attached_by' => $this->resource->pivot->attached_by,
                'attached_at' => $this->resource->pivot->attached_at === null
                    ? null
                    : Carbon::parse($this->resource->pivot->attached_at),
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
