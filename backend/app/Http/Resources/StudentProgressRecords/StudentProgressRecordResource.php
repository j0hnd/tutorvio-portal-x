<?php

namespace App\Http\Resources\StudentProgressRecords;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProgressRecordResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a student progress record into a public-safe API response.
     *
     * Public fields expose skill progress, teacher comments, milestones, goals,
     * status, and loaded student/teacher summaries using public IDs. Creator,
     * updater, and timestamp audit fields are admin-only. Database primary keys
     * should not be exposed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->publicId($this->resource),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'skill_area' => $this->resource->skill_area,
            'progress_summary_by_skill' => $this->resource->progress_summary_by_skill ?? [],
            'speaking_confidence_rating' => $this->resource->speaking_confidence_rating,
            'vocabulary_progress' => $this->resource->vocabulary_progress,
            'grammar_development' => $this->resource->grammar_development,
            'pronunciation_progress' => $this->resource->pronunciation_progress,
            'lesson_completion_count' => $this->resource->lesson_completion_count,
            'teacher_comments' => $this->resource->teacher_comments,
            'milestone_achievements' => $this->resource->milestone_achievements ?? [],
            'level_movement' => $this->resource->level_movement,
            'goals_completed' => $this->resource->goals_completed ?? [],
            'goals_in_progress' => $this->resource->goals_in_progress ?? [],
            'progress_status' => $this->resource->progress_status,
            'goal_status' => $this->resource->progress_status,
            'recorded_at' => $this->resource->recorded_at,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->publicId($this->resource->student),
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'timezone' => $this->resource->student->timezone,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->publicId($this->resource->teacher),
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
                'timezone' => $this->resource->teacher->timezone,
            ]),
        ];

        if ($this->canViewAdminFields($request)) {
            $data += [
                'created_by' => $this->resource->created_by,
                'updated_by' => $this->resource->updated_by,
                'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->resource->createdBy, $request)),
                'updated_by_user' => $this->whenLoaded('updatedBy', fn () => $this->userSummary($this->resource->updatedBy, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
