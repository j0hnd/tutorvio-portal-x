<?php

namespace App\Http\Resources\StudentProgressRecords;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProgressRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
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
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'timezone' => $this->resource->student->timezone,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->resource->teacher->id,
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
                'timezone' => $this->resource->teacher->timezone,
            ]),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->resource->createdBy?->id,
                'name' => $this->resource->createdBy?->name,
                'email' => $this->resource->createdBy?->email,
            ]),
            'updated_by_user' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->resource->updatedBy?->id,
                'name' => $this->resource->updatedBy?->name,
                'email' => $this->resource->updatedBy?->email,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
