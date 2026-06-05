<?php

namespace App\Http\Requests\StudentProgressRecords;

use App\Models\StudentProgressRecord;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create student progress record requests.
 *
 * Expected roles: Authenticated teachers, staff, or admins allowed by student progress routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreStudentProgressRecordRequest extends FormRequest
{
    /**
     * Resolve public user IDs to internal keys for persistence.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(PublicIdResolver::resolveFields($this->all(), [
            'student_id' => User::class,
            'teacher_id' => User::class,
        ]));
    }

    /**
     * Get validation rules for create student progress record requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'skill_area' => ['required', 'string', Rule::in(StudentProgressRecord::SKILL_AREAS)],
            'progress_summary_by_skill' => ['sometimes', 'nullable', 'array'],
            'progress_summary_by_skill.*' => ['nullable', 'string', 'max:2000'],
            'speaking_confidence_rating' => ['sometimes', 'nullable', 'string', Rule::in(StudentProgressRecord::RATINGS)],
            'vocabulary_progress' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'grammar_development' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'pronunciation_progress' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'lesson_completion_count' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'teacher_comments' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'milestone_achievements' => ['sometimes', 'nullable', 'array'],
            'milestone_achievements.*' => ['string', 'max:2000'],
            'level_movement' => ['sometimes', 'nullable', 'string', Rule::in(StudentProgressRecord::LEVEL_MOVEMENTS)],
            'goals_completed' => ['sometimes', 'nullable', 'array'],
            'goals_completed.*' => ['string', 'max:2000'],
            'goals_in_progress' => ['sometimes', 'nullable', 'array'],
            'goals_in_progress.*' => ['string', 'max:2000'],
            'progress_status' => ['sometimes', 'string', Rule::in(StudentProgressRecord::STATUSES)],
            'goal_status' => ['sometimes', 'string', Rule::in(StudentProgressRecord::STATUSES)],
            'recorded_at' => ['sometimes', 'date'],
        ];
    }
}
