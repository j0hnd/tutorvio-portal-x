<?php

namespace App\Http\Resources\Scheduling;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewAdminFields = $this->canViewAdminFields($request);

        $data = [
            'id' => $this->publicId($this->resource),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'class_type' => $this->resource->class_type,
            'timezone' => $this->resource->timezone,
            'starts_at' => $this->resource->starts_at,
            'ends_at' => $this->resource->ends_at,
            'teacher_blocked_until' => $this->resource->teacher_blocked_until,
            'meeting_url' => $this->resource->meeting_url,
            'notes' => $this->resource->notes,
            'cancelled_at' => $this->resource->cancelled_at,
            'cancellation_reason' => $this->resource->cancellation_reason,
            'student' => $this->whenLoaded('student', fn () => $this->userSummary($this->resource->student, $request)),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->userSummary($this->resource->teacher, $request)),
        ];

        if ($canViewAdminFields) {
            $data += [
                'internal_id' => $this->resource->id,
                'rescheduled_from_id' => $this->whenLoaded('rescheduledFrom', fn () => $this->publicId($this->resource->rescheduledFrom)),
                'created_by' => $this->whenLoaded('createdBy', fn () => $this->publicId($this->resource->createdBy)),
                'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->publicId($this->resource->updatedBy)),
                'cancelled_by' => $this->whenLoaded('cancelledBy', fn () => $this->publicId($this->resource->cancelledBy)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
