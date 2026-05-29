<?php

namespace App\Http\Resources\ScheduleChangeRequests;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleChangeRequestResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewAdminFields = $user?->hasRole('admin')
            || ($user?->hasRole('staff') && $user->can('schedule_change_requests.view'))
            || ($user?->hasRole('staff') && $user->can('schedule_change_requests.manage'));
        $canViewReviewer = $canViewAdminFields
            || ($user !== null && (int) $this->resource->requester_id === (int) $user->id);

        $data = [
            'id' => $this->resource->id,
            'requester_id' => $this->resource->requester_id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'lesson_id' => $this->resource->lesson_id,
            'class_schedule_id' => $this->resource->class_schedule_id,
            'current_starts_at' => $this->resource->current_starts_at,
            'current_ends_at' => $this->resource->current_ends_at,
            'requested_starts_at' => $this->resource->requested_starts_at,
            'requested_ends_at' => $this->resource->requested_ends_at,
            'timezone' => $this->resource->timezone,
            'reason' => $this->resource->reason,
            'status' => $this->resource->status,
            'reviewed_at' => $this->resource->reviewed_at,
            'reviewed_by' => $this->when($canViewReviewer, $this->resource->reviewed_by),
            'requester' => $this->whenLoaded('requester', fn () => $this->userSummary($this->resource->requester, $request)),
            'student' => $this->whenLoaded('student', fn () => $this->userSummary($this->resource->student, $request)),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->userSummary($this->resource->teacher, $request)),
            'lesson' => $this->whenLoaded('lesson', fn () => $this->resource->lesson ? [
                'id' => $this->resource->lesson->id,
                'student_id' => $this->resource->lesson->student_id,
                'teacher_id' => $this->resource->lesson->teacher_id,
                'start_time' => $this->resource->lesson->start_time,
                'end_time' => $this->resource->lesson->end_time,
                'status' => $this->resource->lesson->status,
            ] : null),
            'class_schedule' => $this->whenLoaded('classSchedule', fn () => $this->resource->classSchedule ? [
                'id' => $this->resource->classSchedule->id,
                'student_id' => $this->resource->classSchedule->student_id,
                'teacher_id' => $this->resource->classSchedule->teacher_id,
                'starts_at' => $this->resource->classSchedule->starts_at,
                'ends_at' => $this->resource->classSchedule->ends_at,
                'timezone' => $this->resource->classSchedule->timezone,
                'status' => $this->resource->classSchedule->status,
            ] : null),
        ];

        if ($canViewAdminFields) {
            $data += [
                'review_notes' => $this->resource->review_notes,
                'reviewer' => $this->when($this->resource->relationLoaded('reviewer'), fn () => $this->userSummary($this->resource->reviewer, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
