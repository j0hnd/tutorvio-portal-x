<?php

namespace App\Http\Requests\CourseCatalog;

use App\Models\CourseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $courseType = $this->route('courseType');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('course_types', 'name')
                    ->where(fn ($query) => $query->where('is_archived', false))
                    ->ignore($courseType instanceof CourseType ? $courseType->id : null),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
