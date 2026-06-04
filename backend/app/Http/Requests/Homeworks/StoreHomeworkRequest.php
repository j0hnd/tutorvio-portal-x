<?php

namespace App\Http\Requests\Homeworks;

use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates create homework requests.
 *
 * Expected roles: Authenticated students, teachers, staff, or admins allowed by homework routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreHomeworkRequest extends FormRequest
{
    /**
     * Resolve public references to the internal keys expected by homework services.
     */
    protected function prepareForValidation(): void
    {
        $resolved = PublicIdResolver::resolveFields($this->all(), [
            'lesson_id' => Lesson::class,
            'student_id' => User::class,
        ]);

        if ($this->has('documents')) {
            $resolved['documents'] = collect($this->input('documents', []))
                ->map(fn (mixed $id) => PublicIdResolver::toKey($id, LearningResource::class))
                ->all();
        }

        $this->merge($resolved);
    }

    /**
     * Get validation rules for create homework requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; exists rules require referenced records to be present; distinct rules reject duplicate IDs in arrays; required rules define the minimum payload for creation.
     *
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
