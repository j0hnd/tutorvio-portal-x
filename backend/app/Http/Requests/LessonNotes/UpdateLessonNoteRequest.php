<?php

namespace App\Http\Requests\LessonNotes;

use App\Models\LessonRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates update lesson note requests.
 *
 * Expected roles: Authenticated teachers, staff, or admins allowed by lesson note routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateLessonNoteRequest extends FormRequest
{
    /**
     * Resolve accepted public ULID references to internal numeric IDs before integer exists rules run.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'lesson_record_id' => $this->resolvePublicId($this->input('lesson_record_id'), LessonRecord::class),
        ]);
    }

    /**
     * Get validation rules for update lesson note requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; exists rules require referenced records to be present; public_id exists rules expect public identifiers instead of numeric primary keys; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lesson_record_id' => ['sometimes', 'nullable', 'integer', 'exists:lesson_records,id'],
            'lesson_objective' => ['sometimes', 'nullable', 'string'],
            'topics_covered' => ['sometimes', 'nullable', 'string'],
            'vocabulary_learned' => ['sometimes', 'nullable', 'string'],
            'grammar_focus' => ['sometimes', 'nullable', 'string'],
            'pronunciation_issues' => ['sometimes', 'nullable', 'string'],
            'student_speaking_confidence_observation' => ['sometimes', 'nullable', 'string'],
            'homework_assignment' => ['sometimes', 'nullable', 'string'],
            'recommendation_for_next_lesson' => ['sometimes', 'nullable', 'string'],
            'internal_note' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Resolve a public ULID to the internal primary key expected by the existing validation rules.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function resolvePublicId(mixed $value, string $modelClass): mixed
    {
        if (! is_string($value) || ! Str::isUlid($value)) {
            return $value;
        }

        return $modelClass::query()->where('public_id', $value)->value('id') ?? $value;
    }
}
