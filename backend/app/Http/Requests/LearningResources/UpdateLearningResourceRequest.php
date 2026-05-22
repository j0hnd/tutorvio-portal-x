<?php

namespace App\Http\Requests\LearningResources;

use App\Models\LearningResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearningResourceRequest extends FormRequest
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
        $resource = $this->route('learningResource');
        $resourceTypes = $resource instanceof LearningResource && $resource->isExternalLink()
            ? [LearningResource::TYPE_LINK]
            : array_values(array_diff(LearningResource::RESOURCE_TYPES, [LearningResource::TYPE_LINK]));

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'resource_type' => ['sometimes', 'required', 'string', Rule::in($resourceTypes)],
            'url' => [
                'sometimes',
                $resource instanceof LearningResource && $resource->isExternalLink() ? 'required' : 'prohibited',
                'url',
                'max:2048',
            ],
            'course' => ['sometimes', 'nullable', 'string', 'max:255'],
            'level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', Rule::in(LearningResource::VISIBILITIES)],
        ];
    }
}
