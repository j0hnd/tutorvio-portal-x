<?php

namespace App\Http\Resources\IssueReports;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\CourseProgram;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueReportResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewSensitiveDetails = $user?->hasRole('admin')
            || ($user?->hasRole('staff') && (
                $user->can('issue_reports.view')
                || $user->can('issue_reports.manage')
                || $user->can('issue_reports.resolve')
            ));

        $data = [
            'id' => $this->publicId($this->resource),
            'type' => $this->resource->issue_type,
            'issue_type' => $this->resource->issue_type,
            'status' => $this->resource->status,
            'priority' => $this->resource->priority,
            'reporter_id' => $this->publicIdFor(User::class, $this->resource->reporter_id),
            'target_user_id' => $this->publicIdFor(User::class, $this->resource->target_user_id),
            'lesson_id' => $this->publicIdFor(Lesson::class, $this->resource->lesson_id),
            'class_schedule_id' => $this->publicIdFor(ClassSchedule::class, $this->resource->class_schedule_id),
            'related_student_id' => $this->publicIdFor(User::class, $this->resource->related_student_id),
            'related_teacher_id' => $this->publicIdFor(User::class, $this->resource->related_teacher_id),
            'course_program_id' => $this->publicIdFor(CourseProgram::class, $this->resource->course_program_id),
            'learning_resource_id' => $this->publicIdFor(LearningResource::class, $this->resource->learning_resource_id),
            'title' => $this->resource->title,
            'description' => $this->when($canViewSensitiveDetails, $this->resource->description),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->userSummary($this->resource->reporter, $request)),
        ];

        if ($canViewSensitiveDetails) {
            $data += [
                'assigned_to_id' => $this->publicIdFor(User::class, $this->resource->assigned_to_id),
                'resolution_notes' => $this->resource->resolution_notes,
                'resolved_at' => $this->resource->resolved_at,
                'resolved_by' => $this->publicIdFor(User::class, $this->resource->resolved_by),
                'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->userSummary($this->resource->assignedTo, $request)),
                'comments' => $this->when(
                    $this->resource->relationLoaded('comments'),
                    fn () => IssueCommentResource::collection($this->resource->comments)
                ),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
