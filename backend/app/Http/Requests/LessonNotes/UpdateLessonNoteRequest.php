<?php

namespace App\Http\Requests\LessonNotes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonNoteRequest extends FormRequest
{
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
}
