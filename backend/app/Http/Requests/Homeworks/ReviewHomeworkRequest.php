<?php

namespace App\Http\Requests\Homeworks;

use Illuminate\Foundation\Http\FormRequest;

class ReviewHomeworkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_feedback' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
