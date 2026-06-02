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
            'id' => $this->publicId($this->resource),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'current_teacher_id' => $this->whenLoaded('currentTeacher', fn () => $this->publicId($this->resource->currentTeacher)),
            'approved_teacher_id' => $this->whenLoaded('approvedTeacher', fn () => $this->publicId($this->resource->approvedTeacher)),
            'requested_reason' => $this->resource->requested_reason,
            'preferred_schedule_notes' => $this->resource->preferred_schedule_notes,
            'status' => $this->resource->status,
            'reviewed_at' => $this->resource->reviewed_at,
            'review_reason' => $this->when($canViewAdminFields, $this->resource->review_reason),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->publicId($this->resource->student),
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
            ]),
            'current_teacher' => $this->whenLoaded('currentTeacher', fn () => $this->resource->currentTeacher ? [
                'id' => $this->publicId($this->resource->currentTeacher),
                'name' => $this->resource->currentTeacher->name,
                'email' => $this->resource->currentTeacher->email,
            ] : null),
            'approved_teacher' => $this->whenLoaded('approvedTeacher', fn () => $this->resource->approvedTeacher ? [
                'id' => $this->publicId($this->resource->approvedTeacher),
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
