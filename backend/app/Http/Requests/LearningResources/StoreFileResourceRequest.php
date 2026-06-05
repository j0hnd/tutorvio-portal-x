<?php

namespace App\Http\Requests\LearningResources;

use App\Models\LearningResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create file resource requests.
 *
 * Expected roles: Authenticated teachers, staff, or admins allowed by learning resource routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreFileResourceRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit create file resource requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for create file resource requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; file rules use configured MIME type, extension, and size limits; required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fileTypes = array_values(array_diff(
            LearningResource::RESOURCE_TYPES,
            [LearningResource::TYPE_LINK]
        ));

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'resource_type' => ['required', 'string', Rule::in($fileTypes)],
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', config('learning_resources.allowed_mime_types', [])),
                'extensions:'.implode(',', config('learning_resources.allowed_extensions', [])),
                'max:'.config('learning_resources.max_upload_kilobytes', 10240),
            ],
            'course' => ['sometimes', 'nullable', 'string', 'max:255'],
            'level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', Rule::in(LearningResource::VISIBILITIES)],
        ];
    }
}
