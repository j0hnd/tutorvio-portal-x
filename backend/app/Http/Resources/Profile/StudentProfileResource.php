<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Http\Resources\LearningResources\LearningResourceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a student profile into a public-safe profile response.
     *
     * Public fields expose student profile details, guardian/contact information
     * already approved for the profile API, and loaded user references via public
     * IDs. Database primary keys and private internal records should not be
     * exposed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnProfile = $user->id === $this->resource->user_id;
        $isTeacherAssigned = $user->hasRole('teacher') && $this->resource->assigned_teacher_id === $user->id;
        $isAdminOrStaff = $this->canViewAdminFields($request);

        $data = [
            'id' => $this->studentPublicId(),
            'english_level' => $this->resource->english_level,
            'current_level' => $this->resource->current_level,
            'course' => $this->resource->course,
            'class_type' => $this->resource->class_type,
            'preferences' => $this->resource->preferences,
            'goals' => $this->resource->goals,
            'learning_concerns' => $this->resource->learning_concerns,
        ];

        if ($isAdminOrStaff) {
            $data['assigned_teacher_id'] = $this->publicIdFor(User::class, $this->resource->assigned_teacher_id);
            $data['start_date'] = $this->resource->start_date;
            $data['notes'] = $this->resource->notes;
            $data['teacher_notes'] = $this->resource->teacher_notes;
            $data['internal_notes'] = $this->resource->internal_notes;
        }

        if ($isTeacherAssigned && ! $isAdminOrStaff) {
            $data['start_date'] = $this->resource->start_date;
            $data['teacher_notes'] = $this->resource->teacher_notes;
        }

        // We can optionally load relationships if they are loaded
        if ($isAdminOrStaff && $this->relationLoaded('lessons')) {
            $data['lessons'] = $this->resource->lessons;
        }
        if ($isAdminOrStaff && $this->relationLoaded('attendances')) {
            $data['attendances'] = $this->resource->attendances;
        }
        if ($isAdminOrStaff && $this->relationLoaded('materials')) {
            $data['materials'] = $this->resource->materials;
        }
        if ($this->relationLoaded('learningResources')) {
            $data['learning_resources'] = LearningResourceResource::collection($this->resource->learningResources);
        }
        if ($isAdminOrStaff && $this->relationLoaded('subscriptions')) {
            $data['subscriptions'] = $this->resource->subscriptions;
        }

        return $data;
    }

    private function studentPublicId(): ?string
    {
        if ($this->relationLoaded('user')) {
            return $this->publicId($this->resource->user);
        }

        return User::query()
            ->whereKey($this->resource->user_id)
            ->value('public_id');
    }
}
