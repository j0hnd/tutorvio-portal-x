<?php

namespace App\Http\Requests\Homeworks;

use App\Models\Homework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeworkSummaryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(Homework::STATUSES)],
            'course' => ['sometimes', 'string', 'max:255'],
            'level' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
