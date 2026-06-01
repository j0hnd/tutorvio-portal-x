<?php

namespace App\Http\Requests\LessonNotes;

use App\Models\Lesson;
use App\Models\LessonRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreLessonNoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'lesson_id' => $this->resolvePublicId($this->input('lesson_id'), Lesson::class),
            'lesson_record_id' => $this->resolvePublicId($this->input('lesson_record_id'), LessonRecord::class),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'lesson_record_id' => ['sometimes', 'nullable', 'integer', 'exists:lesson_records,id'],
            'lesson_objective' => ['required_without_all:topics_covered,vocabulary_learned,grammar_focus,pronunciation_issues,student_speaking_confidence_observation,homework_assignment,recommendation_for_next_lesson,internal_note', 'nullable', 'string'],
            'topics_covered' => ['required_without_all:lesson_objective,vocabulary_learned,grammar_focus,pronunciation_issues,student_speaking_confidence_observation,homework_assignment,recommendation_for_next_lesson,internal_note', 'nullable', 'string'],
            'vocabulary_learned' => ['required_without_all:lesson_objective,topics_covered,grammar_focus,pronunciation_issues,student_speaking_confidence_observation,homework_assignment,recommendation_for_next_lesson,internal_note', 'nullable', 'string'],
            'grammar_focus' => ['required_without_all:lesson_objective,topics_covered,vocabulary_learned,pronunciation_issues,student_speaking_confidence_observation,homework_assignment,recommendation_for_next_lesson,internal_note', 'nullable', 'string'],
            'pronunciation_issues' => ['required_without_all:lesson_objective,topics_covered,vocabulary_learned,grammar_focus,student_speaking_confidence_observation,homework_assignment,recommendation_for_next_lesson,internal_note', 'nullable', 'string'],
            'student_speaking_confidence_observation' => ['required_without_all:lesson_objective,topics_covered,vocabulary_learned,grammar_focus,pronunciation_issues,homework_assignment,recommendation_for_next_lesson,internal_note', 'nullable', 'string'],
            'homework_assignment' => ['required_without_all:lesson_objective,topics_covered,vocabulary_learned,grammar_focus,pronunciation_issues,student_speaking_confidence_observation,recommendation_for_next_lesson,internal_note', 'nullable', 'string'],
            'recommendation_for_next_lesson' => ['required_without_all:lesson_objective,topics_covered,vocabulary_learned,grammar_focus,pronunciation_issues,student_speaking_confidence_observation,homework_assignment,internal_note', 'nullable', 'string'],
            'internal_note' => ['required_without_all:lesson_objective,topics_covered,vocabulary_learned,grammar_focus,pronunciation_issues,student_speaking_confidence_observation,homework_assignment,recommendation_for_next_lesson', 'nullable', 'string'],
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
