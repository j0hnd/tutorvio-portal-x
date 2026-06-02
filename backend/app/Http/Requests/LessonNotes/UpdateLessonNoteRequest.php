<?php

namespace App\Http\Requests\LessonNotes;

use App\Models\LessonRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateLessonNoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'lesson_record_id' => $this->resolvePublicId($this->input('lesson_record_id'), LessonRecord::class),
        ]);
    }

    /**
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
