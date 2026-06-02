<?php

namespace App\Http\Requests\LearningResources;

use App\Models\LearningResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update learning resource requests.
 *
 * Expected roles: Authenticated teachers, staff, or admins allowed by learning resource routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateLearningResourceRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit update learning resource requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for update learning resource requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; prohibited rules protect fields that this role or request must not change; enum rules constrain values to the relevant model constants; file rules use configured MIME type, extension, and size limits.
     *
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
            'file' => [
                'sometimes',
                $resource instanceof LearningResource && $resource->isExternalLink() ? 'prohibited' : 'file',
                'mimetypes:'.implode(',', config('learning_resources.allowed_mime_types', [])),
                'extensions:'.implode(',', config('learning_resources.allowed_extensions', [])),
                'max:'.config('learning_resources.max_upload_kilobytes', 10240),
            ],
            'url' => [
                'sometimes',
                $resource instanceof LearningResource && $resource->isExternalLink() ? 'required' : 'prohibited',
                'url',
                'max:2048',
            ],
            'change_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'course' => ['sometimes', 'nullable', 'string', 'max:255'],
            'level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', Rule::in(LearningResource::VISIBILITIES)],
        ];
    }
}
