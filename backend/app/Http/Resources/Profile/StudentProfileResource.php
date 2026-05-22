<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\LearningResources\LearningResourceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnProfile = $user->id === $this->resource->user_id;
        $isTeacherAssigned = $user->hasRole('teacher') && $this->resource->assigned_teacher_id === $user->id;
        $isAdminOrStaff = $user->hasRole(['admin', 'staff']);

        $data = [
            'id' => $this->resource->id,
            'english_level' => $this->resource->english_level,
            'current_level' => $this->resource->current_level,
            'course' => $this->resource->course,
            'class_type' => $this->resource->class_type,
            'preferences' => $this->resource->preferences,
            'goals' => $this->resource->goals,
            'learning_concerns' => $this->resource->learning_concerns,
            'teacher_notes' => $this->resource->teacher_notes,
        ];

        if ($isAdminOrStaff) {
            $data['assigned_teacher_id'] = $this->resource->assigned_teacher_id;
            $data['start_date'] = $this->resource->start_date;
            $data['notes'] = $this->resource->notes;
            $data['internal_notes'] = $this->resource->internal_notes;
        }

        if ($isTeacherAssigned && ! $isAdminOrStaff) {
            $data['start_date'] = $this->resource->start_date;
            $data['notes'] = $this->resource->notes;
            // Teacher shouldn't see internal notes unless specified, but let's say they can see notes.
            // Wait, "teacher can only update allowed teacher-note fields for assigned students"
            // "internal remarks must never be exposed to students"
        }

        // We can optionally load relationships if they are loaded
        if ($this->relationLoaded('lessons')) {
            $data['lessons'] = $this->resource->lessons;
        }
        if ($this->relationLoaded('attendances')) {
            $data['attendances'] = $this->resource->attendances;
        }
        if ($this->relationLoaded('materials')) {
            $data['materials'] = $this->resource->materials;
        }
        if ($this->relationLoaded('learningResources')) {
            $data['learning_resources'] = LearningResourceResource::collection($this->resource->learningResources);
        }
        if ($this->relationLoaded('subscriptions')) {
            $data['subscriptions'] = $this->resource->subscriptions;
        }

        return $data;
    }
}
