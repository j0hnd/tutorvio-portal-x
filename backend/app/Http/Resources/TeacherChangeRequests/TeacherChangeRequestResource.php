<?php

namespace App\Http\Resources\TeacherChangeRequests;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherChangeRequestResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewAdminFields = $user?->hasRole('admin')
            || ($user?->hasRole('staff') && $user->can('teacher_change_requests.view'))
            || ($user?->hasRole('staff') && $user->can('teacher_change_requests.manage'));

        $data = [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'current_teacher_id' => $this->resource->current_teacher_id,
            'approved_teacher_id' => $this->resource->approved_teacher_id,
            'requested_reason' => $this->resource->requested_reason,
            'preferred_schedule_notes' => $this->resource->preferred_schedule_notes,
            'status' => $this->resource->status,
            'reviewed_at' => $this->resource->reviewed_at,
            'review_reason' => $this->when($canViewAdminFields, $this->resource->review_reason),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
            ]),
            'current_teacher' => $this->whenLoaded('currentTeacher', fn () => $this->resource->currentTeacher ? [
                'id' => $this->resource->currentTeacher->id,
                'name' => $this->resource->currentTeacher->name,
                'email' => $this->resource->currentTeacher->email,
            ] : null),
            'approved_teacher' => $this->whenLoaded('approvedTeacher', fn () => $this->resource->approvedTeacher ? [
                'id' => $this->resource->approvedTeacher->id,
                'name' => $this->resource->approvedTeacher->name,
                'email' => $this->resource->approvedTeacher->email,
            ] : null),
        ];

        if ($canViewAdminFields) {
            $data += [
                'reviewed_by' => $this->resource->reviewed_by,
                'admin_notes' => $this->resource->admin_notes,
                'reviewed_by_user' => $this->when($this->resource->relationLoaded('reviewer'), fn () => $this->userSummary($this->resource->reviewer, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
