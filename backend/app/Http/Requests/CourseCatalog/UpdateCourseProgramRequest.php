<?php

namespace App\Http\Requests\CourseCatalog;

use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\LearningResource;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update course program requests.
 *
 * Expected roles: Authenticated catalog managers, typically admin or staff users allowed by controller authorization.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateCourseProgramRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit update course program requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Copy the name alias into title when the canonical title field is absent.
     */
    protected function prepareForValidation(): void
    {
        $resolved = PublicIdResolver::resolveFields($this->all(), [
            'course_type_id' => CourseType::class,
        ]);

        if ($this->has('learning_resource_ids')) {
            $resolved['learning_resource_ids'] = collect($this->input('learning_resource_ids', []))
                ->map(fn (mixed $id) => PublicIdResolver::toKey($id, LearningResource::class))
                ->all();
        }

        $this->merge($resolved);

        if (! $this->has('title') && $this->has('name')) {
            $this->merge(['title' => $this->input('name')]);
        }
    }

    /**
     * Get validation rules for update course program requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; exists rules require referenced records to be present; distinct rules reject duplicate IDs in arrays; catalog uniqueness and existence checks ignore archived records.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $courseProgram = $this->route('courseProgram');

        return [
            'course_type_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('course_types', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('course_programs', 'title')
                    ->where(fn ($query) => $query->where('is_archived', false))
                    ->ignore($courseProgram instanceof CourseProgram ? $courseProgram->id : null),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'placement_level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'number_of_sessions' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'lesson_structure' => ['sometimes', 'required', 'array'],
            'milestones' => ['sometimes', 'nullable', 'array'],
            'learning_resource_ids' => ['sometimes', 'array'],
            'learning_resource_ids.*' => ['integer', 'distinct', 'exists:learning_resources,id'],
        ];
    }
}
