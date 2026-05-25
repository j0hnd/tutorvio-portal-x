<?php

namespace App\Http\Requests\CourseCatalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('title') && $this->has('name')) {
            $this->merge(['title' => $this->input('name')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_type_id' => [
                'required',
                'integer',
                Rule::exists('course_types', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('course_programs', 'title')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'placement_level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'number_of_sessions' => ['required', 'integer', 'min:1', 'max:65535'],
            'lesson_structure' => ['required', 'array'],
            'milestones' => ['sometimes', 'nullable', 'array'],
            'learning_resource_ids' => ['sometimes', 'array'],
            'learning_resource_ids.*' => ['integer', 'distinct', 'exists:learning_resources,id'],
        ];
    }
}
