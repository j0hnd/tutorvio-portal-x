<?php

namespace App\Http\Requests\Homeworks;

use Illuminate\Foundation\Http\FormRequest;

class StoreHomeworkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'documents' => ['sometimes', 'array'],
            'documents.*' => ['integer', 'distinct', 'exists:learning_resources,id'],
            'links' => ['sometimes', 'array'],
            'links.*' => ['string', 'url', 'max:2048'],
        ];
    }
}
